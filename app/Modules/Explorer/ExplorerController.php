<?php
declare(strict_types=1);

namespace TreeForge\Modules\Explorer;

use TreeForge\Core\NodeInspector;
use TreeForge\Core\Workspace;
use Throwable;

class ExplorerController
{
    protected string $root;

    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public function handle(): string
    {
        $workspaceName = (string)($_GET['workspace'] ?? Workspace::PUBLISHED);

        $workspace = new Workspace($this->root, $workspaceName);
        $page = $workspace->loadPage('home');

        return (new ExplorerRenderer())->render(
            $page->all(),
            $workspace->name(),
            $this->workspaceStats()
        );
    }

    protected function workspaceStats(): array
    {
        $stats = [];

        foreach ([Workspace::PUBLISHED, Workspace::DRAFT, Workspace::REVIEW] as $workspaceName) {
            try {
                $workspace = new Workspace($this->root, $workspaceName);

                if ($workspace->hasPage('home')) {
                    $page = $workspace->loadPage('home')->all();
                    $stats[$workspaceName] = ['pages' => 1, 'nodes' => NodeInspector::countNodes($page)];
                } else {
                    $stats[$workspaceName] = ['pages' => 0, 'nodes' => 0];
                }
            } catch (Throwable) {
                $stats[$workspaceName] = ['pages' => 0, 'nodes' => 0];
            }
        }

        return $stats;
    }
}