<?php
declare(strict_types=1);

namespace TreeForge\Modules\ExplorerV2;

use TreeForge\Core\Areas\AreaManager;
use TreeForge\Core\Pages\PageTreeManager;
use TreeForge\Core\Settings\SettingsManager;

class ExplorerV2Controller
{
    public function __construct(protected string $root) {}

    public function handle(): string
    {
        $workspace = $this->workspace();
        $pages = new PageTreeManager($this->root, $workspace);
        $areas = new AreaManager($this->root, $workspace);
        $settings = new SettingsManager($this->root);
        $areaId = $this->areaId();

        if ($areaId !== '') {
            $area = $areas->find($areaId) ?: [
                'id' => $areaId,
                'type' => 'area',
                'title' => ucfirst($areaId),
                'status' => 'draft',
                'visibility' => 'hidden',
                'template' => 'core',
            ];
            $data = $areas->loadData($areaId, $workspace);

            return (new ExplorerV2Renderer())->render([
                'settings' => $settings->all(),
                'pages' => $pages,
                'areas' => $areas,
                'pageTree' => $pages->tree(),
                'areaList' => $areas->all(),
                'currentKind' => 'area',
                'currentPage' => $area,
                'currentPageData' => $data,
                'currentPageId' => $areaId,
                'currentPath' => 'area:' . $areaId,
                'workspace' => $workspace,
                'workspaceStats' => $this->workspaceStats($areaId, 'area'),
                'archiveVersions' => $this->archiveVersions($areaId, 'area'),
            ]);
        }

        $pageId = $this->pageId();
        $page = $pages->find($pageId) ?: $pages->find('home') ?: [
            'id' => 'home', 'title' => 'Home', 'slug' => 'home', 'status' => 'published',
            'language' => 'de', 'template' => 'default', 'parent_id' => null,
        ];
        $pageId = (string)($page['id'] ?? 'home');
        $pageData = $this->loadPageData($pageId, $workspace, $page);

        return (new ExplorerV2Renderer())->render([
            'settings' => $settings->all(),
            'pages' => $pages,
            'areas' => $areas,
            'pageTree' => $pages->tree(),
            'areaList' => $areas->all(),
            'currentKind' => 'page',
            'currentPage' => $page,
            'currentPageData' => $pageData,
            'currentPageId' => $pageId,
            'currentPath' => $pages->pathFor($page),
            'workspace' => $workspace,
            'workspaceStats' => $this->workspaceStats($pageId, 'page'),
            'archiveVersions' => $this->archiveVersions($pageId, 'page'),
        ]);
    }

    protected function pageId(): string
    {
        $page = strtolower((string)($_GET['page'] ?? 'home'));
        return preg_replace('/[^a-z0-9_-]/', '', $page) ?: 'home';
    }

    protected function areaId(): string
    {
        $area = strtolower((string)($_GET['area'] ?? ''));
        return preg_replace('/[^a-z0-9_-]/', '', $area) ?: '';
    }

    protected function workspace(): string
    {
        $workspace = strtolower((string)($_GET['workspace'] ?? 'draft'));
        return in_array($workspace, ['published', 'draft', 'review'], true) ? $workspace : 'draft';
    }

    protected function loadPageData(string $pageId, string $workspace, array $page): array
    {
        $candidates = [];
        $candidates[] = $this->root . '/storage/workspaces/' . $workspace . '/pages/' . $pageId . '.json';

        if ($workspace !== 'draft') {
            $candidates[] = $this->root . '/storage/workspaces/draft/pages/' . $pageId . '.json';
        }

        if ($workspace !== 'published') {
            $candidates[] = $this->root . '/storage/workspaces/published/pages/' . $pageId . '.json';
        }

        $candidates[] = $this->root . '/storage/pages/' . $pageId . '.json';

        foreach (array_unique($candidates) as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $data = json_decode((string)file_get_contents($file), true);

            if (is_array($data)) {
                $data['id'] ??= $pageId;
                $data['title'] ??= (string)($page['title'] ?? $pageId);
                $data['children'] ??= [];
                $data['_loaded_from'] = str_replace($this->root . '/', '', $file);

                return $data;
            }
        }

        return [
            'id' => $pageId,
            'type' => 'page',
            'title' => (string)($page['title'] ?? $pageId),
            'children' => [],
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
    }

    protected function workspaceStats(string $id, string $kind): array
    {
        $stats = [];
        foreach (['published', 'draft', 'review'] as $workspace) {
            if ($kind === 'area') {
                $file = $this->root . '/storage/workspaces/' . $workspace . '/areas/' . $id . '.json';
                $data = file_exists($file) ? json_decode((string)file_get_contents($file), true) : [];
                $stats[$workspace] = ['nodes' => is_array($data) ? $this->countNodes($data) : 0];
                continue;
            }

            $data = $this->loadPageData($id, $workspace, ['id' => $id, 'title' => $id]);
            $stats[$workspace] = ['nodes' => $this->countNodes($data)];
        }
        return $stats;
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

    protected function archiveVersions(string $id, string $kind): array
    {
        $base = $kind === 'area' ? 'areas' : 'pages';
        $dir = $this->root . '/storage/archives/' . $base . '/' . $id;
        if (!is_dir($dir)) return [];
        $items = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $items[] = ['version' => basename($file, '.json'), 'created_at' => date('Y-m-d H:i:s', filemtime($file) ?: time())];
        }
        usort($items, static fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
        return $items;
    }
}