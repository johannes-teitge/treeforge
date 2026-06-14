<?php
declare(strict_types=1);

namespace TreeForge\Modules\Explorer;

use TreeForge\Core\ArchiveManager;
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
        $pageId = $this->pageId();
        $workspaceName = (string)($_GET['workspace'] ?? Workspace::PUBLISHED);
        $pageId = (string)($_GET['page'] ?? 'home');

        $archiveVersion = (string)($_GET['archive'] ?? '');

        if ($archiveVersion !== '') {
            $archive = new ArchiveManager($this->root);
            $pageData = $archive->loadVersion($pageId, $archiveVersion);

            return (new ExplorerRenderer())->render(
                $pageData,
                'archive',
                $this->workspaceStats(),
                null,
                $archive->getVersions($pageId),
                $archiveVersion
            );
        }

        $workspace = new Workspace($this->root, $workspaceName);
        $page = $workspace->loadPage($pageId);

        return (new ExplorerRenderer())->render(
            $page->all(),
            $workspace->name(),
            $this->workspaceStats(),
            $workspace->lastEnsureMessage(),
            (new ArchiveManager($this->root))->getVersions($pageId),
            null
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

    protected function pageId(): string
    {
        $page = strtolower((string)($_GET['page'] ?? 'home'));
        return preg_replace('/[^a-z0-9_-]/', '', $page) ?: 'home';
    }
}