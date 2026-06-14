<?php
declare(strict_types=1);

namespace TreeForge\Modules\ExplorerV3;

use TreeForge\Core\NodeInspector;
use TreeForge\Core\Workspace;
use TreeForge\Modules\Explorer\ExplorerTree;

class ExplorerV3Renderer
{
    public function render(
        array $pageData,
        string $workspace,
        array $workspaceStats,
        ?string $notice = null,
        array $archiveVersions = []
    ): string {
        $pageId = $this->currentPageId();
        $workspaceSafe = htmlspecialchars($workspace, ENT_QUOTES, 'UTF-8');
        $pageTitle = htmlspecialchars((string)($pageData['title'] ?? 'Page'), ENT_QUOTES, 'UTF-8');
        $nodeCount = NodeInspector::countNodes($pageData);
        $tree = (new ExplorerTree())->renderPageTree($pageData);
        $workspaceLinks = $this->workspaceLinks($workspace, $workspaceStats, $pageId);
        $noticeHtml = $notice ? '<div class="tfv3-notice">' . htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') . '</div>' : '';
        $json = htmlspecialchars(json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TreeForge Explorer V3</title>
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/svg+xml" href="/assets/brand/treeforge-icon.svg">
  <link rel="stylesheet" href="/assets/css/brand.css">
  <link rel="stylesheet" href="/assets/css/explorer.css">
  <link rel="stylesheet" href="/assets/css/explorer-v3.css">
</head>
<body>
  <header class="tfv3-header">
    <a href="/admin/" class="tfv3-brand">
      <img src="/assets/brand/treeforge-logo.svg" alt="TreeForge">
    </a>
    <div>
      <h1>Explorer V3</h1>
      <p>Stabiler Explorer · Properties first · Page: <strong>{$pageId}</strong></p>
    </div>
    <nav class="tfv3-topnav">
      <a href="/page.php?page={$pageId}&workspace={$workspaceSafe}" target="_blank" rel="noopener">Frontend</a>
      <a href="/admin/explorer/?page={$pageId}&workspace={$workspaceSafe}">V1</a>
      <a href="/admin/explorer-v2/?page={$pageId}&workspace={$workspaceSafe}">V2</a>
    </nav>
  </header>

  {$noticeHtml}

  <section class="tfv3-workspaces">
    <div>
      <h2>Workspaces</h2>
      <div class="tfv3-workspace-row">{$workspaceLinks}</div>
    </div>
    <div class="tfv3-state-box">
      <strong>Live:</strong> published<br>
      <strong>Editing:</strong> draft only<br>
      <strong>Aktuell:</strong> {$workspaceSafe}
    </div>
  </section>

  <main class="tfv3-shell">
    <section class="tfv3-panel tfv3-tree-panel">
      <div class="tfv3-panel-head">
        <div>
          <h2>Tree</h2>
          <p>{$pageTitle} · {$nodeCount} Nodes · {$workspaceSafe}</p>
        </div>
        <div class="tfv3-actions">
          <button type="button" class="tfv3-btn" id="tfExpandAll">Alle aufklappen</button>
          <button type="button" class="tfv3-btn" id="tfCollapseAll">Alle zuklappen</button>
        </div>
      </div>
      {$tree}
    </section>

    <aside class="tfv3-panel tfv3-editor-panel">
      <div class="tfv3-panel-head">
        <div>
          <h2>Eigenschaften</h2>
          <p id="tfv3EditorHint">Node im Baum auswählen.</p>
        </div>
        <span class="tfv3-badge" id="tfv3ModeBadge">{$workspaceSafe}</span>
      </div>

      <div id="tfv3Empty" class="tfv3-empty">
        Wähle links eine Node aus. Die Eigenschaften erscheinen dann hier.
      </div>

      <div id="tfv3Editor" class="tfv3-editor" hidden></div>

      <details class="tfv3-page-json">
        <summary>Page JSON anzeigen</summary>
        <pre>{$json}</pre>
      </details>
    </aside>
  </main>

  <script>
    window.TreeForgeExplorerV3 = {
      page: "{$pageId}",
      workspace: "{$workspaceSafe}"
    };
  </script>
  <script src="/assets/js/explorer-v3.js"></script>
</body>
</html>
HTML;
    }

    protected function workspaceLinks(string $activeWorkspace, array $stats, string $pageId): string
    {
        $labels = [
            Workspace::PUBLISHED => 'Published',
            Workspace::DRAFT => 'Draft',
            Workspace::REVIEW => 'Review',
        ];

        $html = '';

        foreach ($labels as $key => $label) {
            $active = $key === $activeWorkspace ? ' active' : '';
            $count = (int)($stats[$key]['nodes'] ?? 0);
            $html .= '<a class="tfv3-workspace' . $active . '" href="/admin/explorer-v3/?page=' . rawurlencode($pageId) . '&workspace=' . rawurlencode($key) . '">';
            $html .= '<span class="tfv3-dot"></span>';
            $html .= '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
            $html .= '<strong>' . $count . '</strong>';
            $html .= '</a>';
        }

        return $html;
    }

    protected function currentPageId(): string
    {
        $page = strtolower((string)($_GET['page'] ?? 'home'));
        return preg_replace('/[^a-z0-9_-]/', '', $page) ?: 'home';
    }
}