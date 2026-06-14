<?php
declare(strict_types=1);

namespace TreeForge\Modules\ExplorerV3;

use TreeForge\Core\ArchiveManager;
use TreeForge\Core\NodeInspector;
use TreeForge\Core\Workspace;
use Throwable;

class ExplorerV3Controller
{
    public function __construct(protected string $root)
    {
    }

    public function handle(): string
    {
        $pageId = $this->pageId();
        $workspaceName = (string)($_GET['workspace'] ?? Workspace::DRAFT);
        $workspaceName = in_array($workspaceName, [Workspace::PUBLISHED, Workspace::DRAFT, Workspace::REVIEW], true)
            ? $workspaceName
            : Workspace::DRAFT;

        $workspace = new Workspace($this->root, $workspaceName);
        $page = $workspace->loadPage($pageId);

        return (new ExplorerV3Renderer())->render(
            $page->all(),
            $workspace->name(),
            $this->workspaceStats($pageId),
            $workspace->lastEnsureMessage(),
            (new ArchiveManager($this->root))->getVersions($pageId)
        );
    }

    protected function workspaceStats(string $pageId): array
    {
        $stats = [];

        foreach ([Workspace::PUBLISHED, Workspace::DRAFT, Workspace::REVIEW] as $workspaceName) {
            try {
                $workspace = new Workspace($this->root, $workspaceName);

                if ($workspace->hasPage($pageId)) {
                    $page = $workspace->loadPage($pageId)->all();
                    $stats[$workspaceName] = [
                        'pages' => 1,
                        'nodes' => NodeInspector::countNodes($page),
                    ];
                } else {
                    $stats[$workspaceName] = ['pages' => 0, 'nodes' => 0];
                }
            } catch (Throwable) {
                $stats[$workspaceName] = ['pages' => 0, 'nodes' => 0];
            }
        }

        return $stats;
    }

    protected function pageId(): string
    {
        $page = strtolower((string)($_GET['page'] ?? 'home'));
        return preg_replace('/[^a-z0-9_-]/', '', $page) ?: 'home';
    }
}