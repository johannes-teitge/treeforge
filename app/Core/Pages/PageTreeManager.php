<?php
declare(strict_types=1);

namespace TreeForge\Core\Pages;

/**
 * Workspace-basierte Seitenverwaltung.
 *
 * Neue Wahrheit:
 *   storage/workspaces/draft/pages/*.json
 *   storage/workspaces/review/pages/*.json
 *   storage/workspaces/published/pages/*.json
 *
 * storage/pages/pages.json wird nur noch als Übergangs-Fallback für Metadaten gelesen,
 * aber nicht mehr beschrieben.
 */
class PageTreeManager
{
    protected string $workspace;

    /** @var array<int,string> */
    protected array $workspaces = ['draft', 'review', 'published'];

    public function __construct(protected string $root, string $workspace = 'draft')
    {
        $this->workspace = $this->normalizeWorkspace($workspace);
    }

    public function workspace(): string
    {
        return $this->workspace;
    }

    public function setWorkspace(string $workspace): self
    {
        $clone = clone $this;
        $clone->workspace = $this->normalizeWorkspace($workspace);
        return $clone;
    }

    /**
     * Gibt alle bekannten Seiten als flache Liste zurück.
     * Die bevorzugte Datenquelle ist der aktive Workspace, danach Draft, Review, Published.
     */
    public function all(): array
    {
        $ids = $this->allPageIds();
        $legacy = $this->legacyMetaById();
        $pages = [];
        $position = 10;

        foreach ($ids as $id) {
            $sourceWorkspace = $this->preferredWorkspaceFor($id);
            $data = $this->readPageFile($id, $sourceWorkspace) ?? [];
            $pages[] = $this->normalize($data, $id, $sourceWorkspace, $legacy[$id] ?? [], $position);
            $position += 10;
        }

        usort($pages, static function (array $a, array $b): int {
            $pa = (int)($a['position'] ?? 0);
            $pb = (int)($b['position'] ?? 0);
            if ($pa === $pb) {
                return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
            }
            return $pa <=> $pb;
        });

        return $pages;
    }

    public function find(string $id): ?array
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            return null;
        }

        foreach ($this->all() as $page) {
            if ((string)($page['id'] ?? '') === $id) {
                return $page;
            }
        }

        return null;
    }

    public function create(array $values): array
    {
        $title = trim((string)($values['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('Seitentitel fehlt.');
        }

        $parentId = $this->sanitizeId((string)($values['parent_id'] ?? ''));
        $parentId = $parentId !== '' ? $parentId : null;

        if ($parentId !== null && $this->find($parentId) === null) {
            throw new \RuntimeException('Parent-Seite nicht gefunden.');
        }

        $slug = $this->slug(trim((string)($values['slug'] ?? '')) ?: $title);
        $baseId = $slug;
        $id = $baseId;
        $counter = 2;

        while ($this->pageExistsAnywhere($id)) {
            $id = $baseId . '-' . $counter;
            $counter++;
        }

        $pages = $this->all();
        $page = [
            'id' => $id,
            'type' => 'page',
            'title' => $title,
            'slug' => $slug,
            'parent_id' => $parentId,
            'position' => $this->nextPosition($pages, $parentId),
            'status' => 'draft',
            'visibility' => (string)($values['visibility'] ?? 'visible'),
            'language' => (string)($values['language'] ?? 'de'),
            'template' => (string)($values['template'] ?? 'default'),
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'children' => [],
            'properties' => [
                'seo' => [],
                'layout' => [],
                'advanced' => [],
            ],
        ];

        $this->writePageFile($id, 'draft', $page);

        return $this->find($id) ?? $page;
    }

    public function update(string $id, array $values): array
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            throw new \RuntimeException('Seiten-ID fehlt.');
        }

        $page = $this->draftDataForEditing($id);
        if ($page === null) {
            throw new \RuntimeException('Seite nicht gefunden.');
        }

        $title = trim((string)($values['title'] ?? $page['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('Seitentitel fehlt.');
        }

        $parentId = $this->sanitizeId((string)($values['parent_id'] ?? ''));
        $parentId = $parentId !== '' ? $parentId : null;

        if ($parentId === $id) {
            throw new \RuntimeException('Eine Seite kann nicht ihr eigener Parent sein.');
        }

        if ($parentId !== null && $this->find($parentId) === null) {
            throw new \RuntimeException('Parent-Seite nicht gefunden.');
        }

        if ($parentId !== null && $this->isDescendantOf($parentId, $id)) {
            throw new \RuntimeException('Diese Verschiebung würde eine Seitenschleife erzeugen.');
        }

        $page['id'] = $id;
        $page['type'] = (string)($page['type'] ?? 'page');
        $page['title'] = $title;
        $page['slug'] = $this->slug((string)($page['slug'] ?? $id));
        $page['parent_id'] = $parentId;
        $page['position'] = max(0, (int)($values['position'] ?? $page['position'] ?? 10));
        $page['status'] = (string)($values['status'] ?? $page['status'] ?? 'draft');
        $page['visibility'] = (string)($values['visibility'] ?? $page['visibility'] ?? 'visible');
        $page['language'] = (string)($values['language'] ?? $page['language'] ?? 'de');
        $page['template'] = (string)($values['template'] ?? $page['template'] ?? 'default');
        $page['children'] = is_array($page['children'] ?? null) ? $page['children'] : [];
        $page['updated_at'] = date('c');

        $this->writePageFile($id, 'draft', $page);

        return $this->find($id) ?? $page;
    }

    public function sendToReview(string $id): array
    {
        $id = $this->sanitizeId($id);
        $data = $this->draftDataForEditing($id);
        if ($data === null) {
            throw new \RuntimeException('Draft-Seite nicht gefunden.');
        }

        $data['status'] = 'review';
        $data['updated_at'] = date('c');
        $this->writePageFile($id, 'review', $data);

        return $this->find($id) ?? $data;
    }

    public function publish(string $id): array
    {
        $id = $this->sanitizeId($id);
        $data = $this->draftDataForEditing($id);
        if ($data === null) {
            throw new \RuntimeException('Draft-Seite nicht gefunden.');
        }

        $data['status'] = 'published';
        $data['published_at'] = date('c');
        $data['updated_at'] = date('c');
        $this->writePageFile($id, 'published', $data);

        return $this->find($id) ?? $data;
    }

    public function deleteDraft(string $id): void
    {
        $id = $this->sanitizeId($id);
        if ($id === '') {
            throw new \RuntimeException('Seiten-ID fehlt.');
        }

        $file = $this->pageFile($id, 'draft');
        if (!file_exists($file)) {
            throw new \RuntimeException('Draft-Datei nicht gefunden.');
        }

        $trashDir = $this->root . '/storage/trash/pages/' . date('Ymd-His');
        if (!is_dir($trashDir)) {
            mkdir($trashDir, 0775, true);
        }

        rename($file, $trashDir . '/' . basename($file));
    }

    public function duplicateToDraft(string $id): array
    {
        $id = $this->sanitizeId($id);
        $sourceWorkspace = $this->preferredWorkspaceFor($id);
        $source = $this->readPageFile($id, $sourceWorkspace);
        if (!is_array($source)) {
            throw new \RuntimeException('Quellseite nicht gefunden.');
        }

        $baseTitle = (string)($source['title'] ?? $id) . ' Kopie';
        $baseSlug = $this->slug((string)($source['slug'] ?? $id) . '-kopie');
        $newId = $baseSlug;
        $counter = 2;
        while ($this->pageExistsAnywhere($newId)) {
            $newId = $baseSlug . '-' . $counter;
            $counter++;
        }

        $source['id'] = $newId;
        $source['title'] = $baseTitle;
        $source['slug'] = $newId;
        $source['status'] = 'draft';
        $source['parent_id'] = null;
        $source['created_at'] = date('c');
        $source['updated_at'] = date('c');

        $this->writePageFile($newId, 'draft', $source);
        return $this->find($newId) ?? $source;
    }

    public function tree(): array
    {
        $children = [];

        foreach ($this->all() as $page) {
            $key = (string)($page['parent_id'] ?? '');
            $children[$key][] = $page;
        }

        $build = function (?string $parentId) use (&$build, &$children): array {
            $branch = $children[(string)$parentId] ?? [];
            usort($branch, static fn(array $a, array $b): int => ((int)($a['position'] ?? 0)) <=> ((int)($b['position'] ?? 0)));

            foreach ($branch as $index => $page) {
                $branch[$index]['children'] = $build((string)($page['id'] ?? ''));
            }

            return $branch;
        };

        return $build(null);
    }

    public function pathFor(array $page): string
    {
        $byId = [];
        foreach ($this->all() as $item) {
            $byId[(string)($item['id'] ?? '')] = $item;
        }

        $segments = [(string)($page['slug'] ?? $page['id'] ?? '')];
        $parentId = $page['parent_id'] ?? null;

        while ($parentId && isset($byId[(string)$parentId])) {
            $parent = $byId[(string)$parentId];
            array_unshift($segments, (string)($parent['slug'] ?? $parent['id']));
            $parentId = $parent['parent_id'] ?? null;
        }

        $path = '/' . implode('/', array_filter($segments));
        return $path === '/home' ? '/' : $path;
    }

    /** @return array<int,string> */
    protected function allPageIds(): array
    {
        $ids = [];

        foreach ($this->workspaces as $workspace) {
            $dir = $this->pageDir($workspace);
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $ids[basename($file, '.json')] = true;
            }
        }

        foreach (array_keys($this->legacyMetaById()) as $id) {
            if ($this->pageExistsAnywhere((string)$id)) {
                $ids[(string)$id] = true;
            }
        }

        $ids = array_keys($ids);
        sort($ids, SORT_NATURAL | SORT_FLAG_CASE);
        return $ids;
    }

    protected function preferredWorkspaceFor(string $id): string
    {
        $order = array_values(array_unique([$this->workspace, 'draft', 'review', 'published']));
        foreach ($order as $workspace) {
            if (file_exists($this->pageFile($id, $workspace))) {
                return $workspace;
            }
        }
        return $this->workspace;
    }

    protected function draftDataForEditing(string $id): ?array
    {
        $draft = $this->readPageFile($id, 'draft');
        if (is_array($draft)) {
            return $draft;
        }

        $sourceWorkspace = $this->preferredWorkspaceFor($id);
        $source = $this->readPageFile($id, $sourceWorkspace);
        if (is_array($source)) {
            $source['status'] = 'draft';
            return $source;
        }

        return null;
    }

    protected function readPageFile(string $id, string $workspace): ?array
    {
        $file = $this->pageFile($id, $workspace);
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    protected function writePageFile(string $id, string $workspace, array $data): void
    {
        $workspace = $this->normalizeWorkspace($workspace);
        $file = $this->pageFile($id, $workspace);
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        $data['id'] = $id;
        $data['children'] = is_array($data['children'] ?? null) ? $data['children'] : [];

        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    protected function pageDir(string $workspace): string
    {
        return $this->root . '/storage/workspaces/' . $this->normalizeWorkspace($workspace) . '/pages';
    }

    protected function pageFile(string $id, string $workspace): string
    {
        return $this->pageDir($workspace) . '/' . $this->sanitizeId($id) . '.json';
    }

    protected function pageExistsAnywhere(string $id): bool
    {
        foreach ($this->workspaces as $workspace) {
            if (file_exists($this->pageFile($id, $workspace))) {
                return true;
            }
        }
        return false;
    }

    protected function normalize(array $data, string $fileId, string $sourceWorkspace, array $legacy, int $fallbackPosition): array
    {
        $id = $this->sanitizeId((string)($data['id'] ?? $fileId)) ?: $fileId;
        $slug = $this->slug($this->scalarString($data['slug'] ?? $legacy['slug'] ?? null, $id));
        $title = $this->scalarString($data['title'] ?? $legacy['title'] ?? null, $id);

        $rawStatus = (string)($data['page_status'] ?? $data['workflow_status'] ?? '');
        $status = in_array($rawStatus, ['draft', 'review', 'published', 'hidden'], true)
            ? $rawStatus
            : $sourceWorkspace;

        $page = [
            'id' => $id,
            'type' => (string)($data['type'] ?? 'page'),
            'title' => $title,
            'slug' => $slug,
            'parent_id' => $data['parent_id'] ?? $legacy['parent_id'] ?? null,
            'position' => (int)($data['position'] ?? $legacy['position'] ?? $fallbackPosition),
            'status' => $status,
            'visibility' => $this->scalarString($data['visibility'] ?? $legacy['visibility'] ?? null, 'visible'),
            'language' => $this->scalarString($data['language'] ?? $legacy['language'] ?? null, 'de'),
            'template' => $this->scalarString($data['template'] ?? $legacy['template'] ?? null, 'default'),
            'created_at' => $this->scalarString($data['created_at'] ?? $legacy['created_at'] ?? null, date('c')),
            'updated_at' => $this->scalarString($data['updated_at'] ?? $legacy['updated_at'] ?? null, date('c')), 
            'source_workspace' => $sourceWorkspace,
            'workspaces' => $this->workspacePresence($id),
            'node_count' => $this->countNodes($data),
            'children_count' => count((array)($data['children'] ?? [])),
        ];

        $page['parent_id'] = $page['parent_id'] !== '' ? $page['parent_id'] : null;
        $page['path'] = $this->pathForFlat($page, $legacy);

        return $page;
    }

    protected function pathForFlat(array $page, array $legacy): string
    {
        $slug = (string)($page['slug'] ?? $page['id'] ?? '');
        $path = '/' . trim($slug, '/');
        return $path === '/home' ? '/' : $path;
    }

    /** @return array<string,bool> */
    protected function workspacePresence(string $id): array
    {
        $presence = [];
        foreach ($this->workspaces as $workspace) {
            $presence[$workspace] = file_exists($this->pageFile($id, $workspace));
        }
        return $presence;
    }

    protected function statusFromPresence(string $id): string
    {
        if (file_exists($this->pageFile($id, 'draft'))) {
            return 'draft';
        }
        if (file_exists($this->pageFile($id, 'review'))) {
            return 'review';
        }
        if (file_exists($this->pageFile($id, 'published'))) {
            return 'published';
        }
        return 'draft';
    }

    protected function scalarString(mixed $value, string $fallback = ''): string
    {
        if (is_scalar($value) || $value === null) {
            $string = trim((string)$value);
            return $string !== '' ? $string : $fallback;
        }

        return $fallback;
    }

    /** @return array<string,array> */
    protected function legacyMetaById(): array
    {
        $file = $this->root . '/storage/pages/pages.json';
        if (!file_exists($file)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            return [];
        }

        $map = [];
        foreach ($data as $page) {
            if (is_array($page) && isset($page['id'])) {
                $map[(string)$page['id']] = $page;
            }
        }

        return $map;
    }

    protected function nextPosition(array $pages, ?string $parentId): int
    {
        $max = 0;
        foreach ($pages as $page) {
            if (($page['parent_id'] ?? null) === $parentId) {
                $max = max($max, (int)($page['position'] ?? 0));
            }
        }
        return $max + 10;
    }

    protected function isDescendantOf(string $candidateId, string $parentId): bool
    {
        $byId = [];
        foreach ($this->all() as $page) {
            $byId[(string)($page['id'] ?? '')] = $page;
        }

        $current = $byId[$candidateId] ?? null;
        while ($current) {
            $pid = (string)($current['parent_id'] ?? '');
            if ($pid === '') {
                return false;
            }
            if ($pid === $parentId) {
                return true;
            }
            $current = $byId[$pid] ?? null;
        }

        return false;
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
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'page';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'page';
    }
}