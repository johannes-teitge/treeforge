<?php
declare(strict_types=1);

namespace TreeForge\Core\Areas;

/**
 * Workspace-basierte globale Bereiche.
 *
 * Areas sind keine routbaren Seiten, sondern wiederverwendbare Inhaltsbereiche
 * für Templates: Header, Footer, Sidebar, CTA, Cookie-Hinweis usw.
 *
 * Speicherorte:
 *   storage/workspaces/draft/areas/footer.json
 *   storage/workspaces/review/areas/footer.json
 *   storage/workspaces/published/areas/footer.json
 */
class AreaManager
{
    /** @var array<int,string> */
    protected array $workspaces = ['draft', 'review', 'published'];

    public function __construct(protected string $root, protected string $workspace = 'draft')
    {
        $this->workspace = $this->normalizeWorkspace($workspace);
    }

    public function workspace(): string
    {
        return $this->workspace;
    }

    public function all(): array
    {
        $ids = $this->allAreaIds();
        $areas = [];
        $position = 10;

        foreach ($ids as $id) {
            $sourceWorkspace = $this->preferredWorkspaceFor($id);
            $data = $this->readAreaFile($id, $sourceWorkspace) ?? [];
            $areas[] = $this->normalize($data, $id, $sourceWorkspace, $position);
            $position += 10;
        }

        usort($areas, static function (array $a, array $b): int {
            $pa = (int)($a['position'] ?? 0);
            $pb = (int)($b['position'] ?? 0);
            if ($pa === $pb) {
                return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
            }
            return $pa <=> $pb;
        });

        return $areas;
    }

    public function find(string $id): ?array
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            return null;
        }

        foreach ($this->all() as $area) {
            if ((string)($area['id'] ?? '') === $id) {
                return $area;
            }
        }

        return null;
    }

    public function create(array $values): array
    {
        $title = trim((string)($values['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('Bereichstitel fehlt.');
        }

        $baseId = $this->sanitizeId((string)($values['id'] ?? '')) ?: $this->slug($title);
        $id = $baseId;
        $counter = 2;

        while ($this->areaExistsAnywhere($id)) {
            $id = $baseId . '-' . $counter;
            $counter++;
        }

        $area = $this->defaultAreaData($id, $title);
        $area['position'] = $this->nextPosition();
        $area['description'] = trim((string)($values['description'] ?? ''));
        $area['updated_at'] = date('c');
        $area['created_at'] = date('c');

        $this->writeAreaFile($id, 'draft', $area);

        return $this->find($id) ?? $area;
    }

    public function update(string $id, array $values): array
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            throw new \RuntimeException('Bereichs-ID fehlt.');
        }

        $data = $this->loadData($id, 'draft');
        $title = trim((string)($values['title'] ?? $data['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('Bereichstitel fehlt.');
        }

        $data['title'] = $title;
        $data['description'] = trim((string)($values['description'] ?? $data['description'] ?? ''));
        $data['position'] = (int)($values['position'] ?? $data['position'] ?? 10);
        $data['visibility'] = (string)($values['visibility'] ?? $data['visibility'] ?? 'hidden');
        $data['status'] = 'draft';
        $data['updated_at'] = date('c');

        $this->writeAreaFile($id, 'draft', $data);

        return $this->find($id) ?? $this->normalize($data, $id, 'draft', 10);
    }

    public function sendToReview(string $id): void
    {
        $id = $this->sanitizeId($id);
        $draft = $this->areaFile($id, 'draft');
        if (!file_exists($draft)) {
            throw new \RuntimeException('Draft-Bereich nicht gefunden.');
        }

        $data = $this->loadData($id, 'draft');
        $data['status'] = 'review';
        $data['updated_at'] = date('c');
        $this->writeAreaFile($id, 'review', $data);
    }

    public function publish(string $id): void
    {
        $id = $this->sanitizeId($id);
        $source = file_exists($this->areaFile($id, 'draft')) ? 'draft' : 'review';
        $file = $this->areaFile($id, $source);
        if (!file_exists($file)) {
            throw new \RuntimeException('Bereich nicht gefunden.');
        }

        $data = $this->loadData($id, $source);
        $data['status'] = 'published';
        $data['updated_at'] = date('c');
        $this->writeAreaFile($id, 'published', $data);
    }

    public function deleteDraft(string $id): void
    {
        $id = $this->sanitizeId($id);
        $file = $this->areaFile($id, 'draft');
        if (!file_exists($file)) {
            throw new \RuntimeException('Draft-Bereich nicht gefunden.');
        }

        $trash = $this->root . '/storage/trash/areas/' . $id . '-' . date('Ymd-His') . '.json';
        if (!is_dir(dirname($trash))) {
            mkdir(dirname($trash), 0775, true);
        }

        rename($file, $trash);
    }

    public function loadData(string $id, string $workspace): array
    {
        $id = $this->sanitizeId($id);
        $workspace = $this->normalizeWorkspace($workspace);
        $candidates = [
            $this->areaFile($id, $workspace),
        ];

        if ($workspace !== 'draft') {
            $candidates[] = $this->areaFile($id, 'draft');
        }
        if ($workspace !== 'published') {
            $candidates[] = $this->areaFile($id, 'published');
        }

        foreach (array_unique($candidates) as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) {
                return $this->normalizeAreaRoot($data, $id);
            }
        }

        return $this->defaultAreaData($id, ucfirst($id));
    }

    public function writeData(string $id, string $workspace, array $data): void
    {
        $id = $this->sanitizeId($id);
        $workspace = $this->normalizeWorkspace($workspace);
        $this->writeAreaFile($id, $workspace, $this->normalizeAreaRoot($data, $id));
    }

    public function pathFor(array $area): string
    {
        return 'area:' . (string)($area['id'] ?? '');
    }

    protected function normalize(array $data, string $fileId, string $sourceWorkspace, int $fallbackPosition): array
    {
        $id = $this->sanitizeId((string)($data['id'] ?? $fileId)) ?: $fileId;
        $title = trim((string)($data['title'] ?? ucfirst($id))) ?: ucfirst($id);
        $rawStatus = (string)($data['status'] ?? '');
        $status = in_array($rawStatus, ['draft', 'review', 'published', 'hidden'], true) ? $rawStatus : $sourceWorkspace;

        return [
            'id' => $id,
            'type' => 'area',
            'root_type' => (string)($data['type'] ?? 'RootNode'),
            'title' => $title,
            'description' => trim((string)($data['description'] ?? '')),
            'position' => (int)($data['position'] ?? $fallbackPosition),
            'status' => $status,
            'visibility' => trim((string)($data['visibility'] ?? 'hidden')) ?: 'hidden',
            'system' => (bool)($data['system'] ?? true),
            'source_workspace' => $sourceWorkspace,
            'workspaces' => $this->workspacePresence($id),
            'node_count' => $this->countNodes($data),
            'children_count' => count((array)($data['children'] ?? [])),
            'created_at' => (string)($data['created_at'] ?? date('c')),
            'updated_at' => (string)($data['updated_at'] ?? date('c')),
        ];
    }

    protected function normalizeAreaRoot(array $data, string $id): array
    {
        $data['id'] = $this->sanitizeId((string)($data['id'] ?? $id)) ?: $id;
        // Für den Explorer bleibt die Area-Root ein RootNode, damit bestehende Root-Regeln greifen.
        $data['type'] = (string)($data['type'] ?? 'RootNode');
        if (strtolower($data['type']) === 'area') {
            $data['type'] = 'RootNode';
        }
        $data['kind'] = 'area';
        $data['title'] = trim((string)($data['title'] ?? ucfirst($id))) ?: ucfirst($id);
        $data['visibility'] ??= 'hidden';
        $data['system'] ??= true;
        $data['children'] ??= [];
        $data['properties'] ??= ['content' => [], 'layout' => [], 'spacing' => [], 'design' => [], 'behavior' => [], 'advanced' => [], 'custom_css' => ''];
        return $data;
    }

    protected function defaultAreaData(string $id, string $title): array
    {
        return [
            'id' => $id,
            'type' => 'RootNode',
            'kind' => 'area',
            'title' => $title,
            'description' => '',
            'status' => 'draft',
            'visibility' => 'hidden',
            'system' => true,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'children' => [],
            'properties' => [
                'content' => [],
                'layout' => [],
                'spacing' => [],
                'design' => [],
                'behavior' => [],
                'advanced' => [],
                'custom_css' => '',
            ],
        ];
    }

    protected function allAreaIds(): array
    {
        $ids = [];
        foreach ($this->workspaces as $workspace) {
            foreach (glob($this->root . '/storage/workspaces/' . $workspace . '/areas/*.json') ?: [] as $file) {
                $ids[basename($file, '.json')] = true;
            }
        }

        $ids = array_keys($ids);
        sort($ids);
        return $ids;
    }

    protected function preferredWorkspaceFor(string $id): string
    {
        $order = [$this->workspace, 'draft', 'review', 'published'];
        foreach (array_unique($order) as $workspace) {
            if (file_exists($this->areaFile($id, $workspace))) {
                return $workspace;
            }
        }
        return $this->workspace;
    }

    protected function readAreaFile(string $id, string $workspace): ?array
    {
        $file = $this->areaFile($id, $workspace);
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    protected function writeAreaFile(string $id, string $workspace, array $data): void
    {
        $file = $this->areaFile($id, $workspace);
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Area JSON konnte nicht erzeugt werden: ' . json_last_error_msg());
        }

        file_put_contents($file, $json, LOCK_EX);
    }

    protected function nextPosition(): int
    {
        $max = 0;
        foreach ($this->all() as $area) {
            $max = max($max, (int)($area['position'] ?? 0));
        }
        return $max + 10;
    }

    protected function areaExistsAnywhere(string $id): bool
    {
        foreach ($this->workspaces as $workspace) {
            if (file_exists($this->areaFile($id, $workspace))) {
                return true;
            }
        }
        return false;
    }

    protected function workspacePresence(string $id): array
    {
        $presence = [];
        foreach ($this->workspaces as $workspace) {
            $presence[$workspace] = file_exists($this->areaFile($id, $workspace));
        }
        return $presence;
    }

    protected function countNodes(array $node): int
    {
        $count = 1;
        foreach ((array)($node['children'] ?? []) as $child) {
            if (is_array($child)) {
                $count += $this->countNodes($child);
            }
        }
        return $count;
    }

    protected function areaFile(string $id, string $workspace): string
    {
        return $this->root . '/storage/workspaces/' . $workspace . '/areas/' . $id . '.json';
    }

    protected function normalizeWorkspace(string $workspace): string
    {
        $workspace = strtolower(trim($workspace));
        return in_array($workspace, $this->workspaces, true) ? $workspace : 'draft';
    }

    protected function sanitizeId(string $id): string
    {
        $id = strtolower(trim($id));
        return preg_replace('/[^a-z0-9_-]/', '', $id) ?: '';
    }

    protected function slug(string $value): string
    {
        $value = trim($value);
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'area';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'area';
    }
}