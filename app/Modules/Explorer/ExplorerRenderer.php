<?php
declare(strict_types=1);

namespace TreeForge\Modules\Explorer;

use TreeForge\Core\NodeInspector;

class ExplorerRenderer
{
    protected function currentPageId(): string
    {
        $page = strtolower((string)($_GET['page'] ?? 'home'));
        return preg_replace('/[^a-z0-9_-]/', '', $page) ?: 'home';
    }

    public function render(
        array $pageData,
        string $workspace,
        array $workspaceStats,
        ?string $notice = null,
        array $archiveVersions = [],
        ?string $selectedArchiveVersion = null
    ): string {
        $pageId = $this->currentPageId();
        $pageQuery = 'page=' . rawurlencode($pageId);
        $tree = (new ExplorerTree())->renderPageTree($pageData);
        $workspaceSafe = htmlspecialchars($workspace, ENT_QUOTES, 'UTF-8');

        $noticeHtml = '';

        if ($notice !== null && $notice !== '') {
            $noticeHtml = '<div class="tf-notice">' . htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $workspaces = [
            'published' => 'Published',
            'draft' => 'Draft',
            'review' => 'Review',
        ];

        $workspaceLinks = '';

        foreach ($workspaces as $key => $label) {
            $active = $key === $workspace ? ' active' : '';
            $count = (int)($workspaceStats[$key]['nodes'] ?? 0);

            $workspaceLinks .= '<a class="tf-workspace-link' . $active . '" href="/admin/explorer/?workspace=' . $key . '&' . $pageQuery . '">';
            $workspaceLinks .= '<span class="tf-dot"></span>';
            $workspaceLinks .= '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
            $workspaceLinks .= '<span class="tf-count">' . $count . '</span>';
            $workspaceLinks .= '</a>';
        }

        $archiveLinks = $this->archiveLinks($archiveVersions, $selectedArchiveVersion);
        $workflowActions = $this->workflowActions($workspace, $selectedArchiveVersion);

        $pageJson = htmlspecialchars(
            json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            ENT_QUOTES,
            'UTF-8'
        );

        $nodeCount = NodeInspector::countNodes($pageData);
        $pageTitle = htmlspecialchars((string)($pageData['title'] ?? 'Page'), ENT_QUOTES, 'UTF-8');
        $workflowStatus = htmlspecialchars((string)($pageData['_workflow']['status'] ?? $workspace), ENT_QUOTES, 'UTF-8');

        $archiveBadge = $selectedArchiveVersion
            ? '<span class="tf-badge archive">archive ' . htmlspecialchars($selectedArchiveVersion, ENT_QUOTES, 'UTF-8') . '</span>'
            : '<span class="tf-badge">' . $workspaceSafe . '</span>';

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TreeForge Explorer</title>
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/svg+xml" href="/assets/brand/treeforge-icon.svg">
  <link rel="stylesheet" href="/assets/css/brand.css">
  <link rel="stylesheet" href="/assets/css/explorer.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1/themes/prism-tomorrow.min.css">
</head>
<body>
  <header class="tf-explorer-header">
    <a href="/" class="tf-brand-link">
      <img src="/assets/brand/treeforge-logo.svg" alt="TreeForge" class="tf-explorer-logo">
    </a>
    <div>
      <h1>Explorer</h1>
      <p>Structure first. Content grows in Layers.</p><p class="tf-current-page-info">Page: <strong>{$pageId}</strong></p>
    </div>
  </header>

  {$noticeHtml}

  <main class="tf-explorer-shell">
    <aside class="tf-panel tf-workspaces">
      <h2>Workspaces</h2>
      {$workspaceLinks}

      <div class="tf-workspace-note">
        <strong>Live:</strong> published<br>
        <strong>Editing:</strong> draft only
      </div>

      <h2 class="tf-aside-subtitle">Archive</h2>
      {$archiveLinks}
    </aside>

    <section class="tf-panel tf-tree-panel">
      <div class="tf-panel-head">
        <h2>Tree</h2>
        {$archiveBadge}
      </div>

      <div class="tf-workflow-box">
        <div>
          <strong>Workflow Status</strong><br>
          <span>{$workflowStatus}</span>
        </div>
        <div class="tf-workflow-actions">
          {$workflowActions}
        </div>
      </div>

      <div class="tf-tree-toolbar"><button type="button" class="tf-tree-tool primary" id="tfAddNode">+ Node</button><button type="button" class="tf-tree-tool" id="tfExpandAll">Alle aufklappen</button><button type="button" class="tf-tree-tool" id="tfCollapseAll">Alle zuklappen</button></div>

      {$tree}

      <footer class="tf-panel-footer">
        <span>Page: {$pageTitle}</span><span>ID: {$pageId}</span>
        <span>Nodes: {$nodeCount}</span>
      </footer>
    </section>

    <section class="tf-panel tf-inspector">
      <div class="tf-panel-head">
        <h2>Inspector</h2>
        <span class="tf-badge" id="tfInspectorMode">readonly</span>
      </div>

      <div class="tf-inspector-empty" id="tfInspectorEmpty">
        Node im Baum auswählen.
      </div>

      <div class="tf-inspector-content" id="tfInspectorContent" hidden>
        <section class="tf-inspector-section">
          <h3>Node Information</h3>
          <dl>
            <dt>Node ID</dt>
            <dd id="tfInspectorId">–</dd>
            <dt>Node Type</dt>
            <dd id="tfInspectorType">–</dd>
            <dt>Workspace</dt>
            <dd>{$workspaceSafe}</dd>
            <dt>Children</dt>
            <dd id="tfInspectorChildren">0</dd>
          </dl>
        </section>

        <section class="tf-inspector-section tf-editor-section" id="tfTextEditorSection" hidden>
          <h3>TextNode Editor</h3>
          <p class="tf-editor-hint">Speichert immer in den Draft Workspace.</p>
          <textarea id="tfTextEditor" class="tf-textarea" rows="8"></textarea>
          <div class="tf-editor-actions">
            <button type="button" id="tfSaveTextNode" class="tf-action-button">In Draft speichern</button>
            <span id="tfSaveStatus" class="tf-save-status"></span>
          </div>
        </section>

        <section class="tf-inspector-section tf-editor-section" id="tfMarkdownEditorSection" hidden>
          <h3>Markdown Editor</h3>
          <p class="tf-editor-hint">Speichert immer in den Draft Workspace.</p>
          <textarea id="tfMarkdownEditor" class="tf-textarea" rows="10"></textarea>
          <div class="tf-editor-actions">
            <button type="button" id="tfSaveMarkdownNode" class="tf-action-button">Markdown in Draft speichern</button>
            <span id="tfMarkdownSaveStatus" class="tf-save-status"></span>
          </div>
        </section>

        <section class="tf-inspector-section" id="tfPreviewSection" hidden>
          <h3>Preview</h3>
          <div id="tfMarkdownPreview" class="tf-markdown-preview" hidden></div>
          <pre class="tf-code-preview"><code id="tfPreviewCode"></code></pre>
        </section>

        <section class="tf-inspector-section">
          <h3>Properties</h3>
          <div id="tfInspectorProperties" class="tf-properties"></div>
        </section>

        <section class="tf-inspector-section">
          <h3>JSON</h3>
          <pre id="tfInspectorJson">{}</pre>
        </section>
      </div>

      <details class="tf-page-json">
        <summary>Komplette Page JSON anzeigen</summary>
        <pre>{$pageJson}</pre>
      </details>
    </section>
  </main>

  <script>
    window.TreeForgeExplorer = {
      workspace: "{$workspaceSafe}",
      page: "{$pageId}",
      archive: "{$selectedArchiveVersion}"
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1/prism.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1/components/prism-css.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1/components/prism-markdown.min.js"></script>
  <script src="/assets/js/explorer.js?v=030"></script>

  <div class="tf-modal-backdrop" id="tfNodeWizard" hidden>
    <div class="tf-modal" role="dialog" aria-modal="true" aria-labelledby="tfNodeWizardTitle">
      <div class="tf-modal-head">
        <h2 id="tfNodeWizardTitle">Node hinzufügen</h2>
        <button type="button" class="tf-modal-close" id="tfNodeWizardClose" aria-label="Schließen">×</button>
      </div>

      <div class="tf-modal-body">
        <div class="tf-form-row">
          <label for="tfNodeType">Node Typ</label>
          <select id="tfNodeType" class="tf-input">
            <option value="text">Text</option>
            <option value="image">Image</option>
            <option value="button">Button</option>
            <option value="markdown">Markdown</option>
            <option value="css">CSS</option>
            <option value="columns">Columns</option>
          </select>
        </div>

        <div class="tf-form-row" id="tfColumnsOptions" hidden>
          <label>Spaltenanzahl</label>
          <div class="tf-segmented">
            <label><input type="radio" name="tfColumnsCount" value="2" checked><span>2</span></label>
            <label><input type="radio" name="tfColumnsCount" value="3"><span>3</span></label>
            <label><input type="radio" name="tfColumnsCount" value="4"><span>4</span></label>
            <label><input type="radio" name="tfColumnsCount" value="5"><span>5</span></label>
            <label><input type="radio" name="tfColumnsCount" value="6"><span>6</span></label>
          </div>

          <label for="tfColumnsGap" class="mt-small">Gap</label>
          <input id="tfColumnsGap" class="tf-input" value="1rem">
        </div>

        <div class="tf-wizard-info" id="tfNodeWizardInfo">
          Neue Node wird am Ende der Startseite angelegt.
        </div>
      </div>

      <div class="tf-modal-actions">
        <button type="button" class="tf-action-button secondary" id="tfNodeWizardCancel">Abbrechen</button>
        <button type="button" class="tf-action-button" id="tfNodeWizardCreate">Anlegen</button>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    protected function archiveLinks(array $archiveVersions, ?string $selectedArchiveVersion): string
    {
        if ($archiveVersions === []) {
            return ''
                . '<div class="tf-archive-empty">Noch keine Archivversionen.</div>'
                . '<a class="tf-archive-link all" href="/archives?page={$pageId}"><span>📦</span><span>Archive Center öffnen</span></a>';
        }

        $visibleVersions = array_slice($archiveVersions, 0, 5);

        $html = '<div class="tf-archive-list">';

        foreach ($visibleVersions as $version) {
            $id = (string)$version['version'];
            $label = (string)($version['created_at'] ?? $id);
            $active = $selectedArchiveVersion === $id ? ' active' : '';

            $html .= '<a class="tf-archive-link' . $active . '" href="/admin/explorer/?archive=' . rawurlencode($id) . '&page={$pageId}">';
            $html .= '<span>🕘</span><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
            $html .= '</a>';
        }

        if (count($archiveVersions) > 5) {
            $html .= '<div class="tf-archive-more">+' . (count($archiveVersions) - 5) . ' weitere Archivversion(en)</div>';
        }

        $html .= '<a class="tf-archive-link all" href="/archives?page={$pageId}"><span>📦</span><span>Alle Archive anzeigen</span></a>';
        $html .= '</div>';

        return $html;
    }
    protected function workflowActions(string $workspace, ?string $selectedArchiveVersion): string
    {
        if ($selectedArchiveVersion !== null && $selectedArchiveVersion !== '') {
            $version = htmlspecialchars($selectedArchiveVersion, ENT_QUOTES, 'UTF-8');
            $versionUrl = rawurlencode($selectedArchiveVersion);

            return ''
                . '<a class="tf-workflow-link preview" href="/?archive=' . $versionUrl . '&page={$pageId}" target="_blank" rel="noopener">Archiv ansehen</a>'
                . '<button type="button" class="tf-workflow-button danger" data-archive-restore="' . $version . '">Archivversion wiederherstellen</button>'
                . '<a class="tf-workflow-link secondary" href="/admin/explorer/?workspace=published">Zurück zu Published</a>';
        }

        return match ($workspace) {
            'published' => ''
                . '<a class="tf-workflow-link preview" href="/" target="_blank" rel="noopener">Live ansehen</a>'
                . '<a class="tf-workflow-link secondary" href="/admin/explorer/?workspace=draft">Draft bearbeiten</a>',

            'draft' => ''
                . '<a class="tf-workflow-link preview" href="/?workspace=draft" target="_blank" rel="noopener">Draft Preview</a>'
                . '<button type="button" class="tf-workflow-button" data-workflow-action="send_to_review">In Review senden</button>',

            'review' => ''
                . '<a class="tf-workflow-link preview" href="/?workspace=review" target="_blank" rel="noopener">Review Preview</a>'
                . '<button type="button" class="tf-workflow-button" data-workflow-action="publish_review">Freigeben & veröffentlichen</button>'
                . '<button type="button" class="tf-workflow-button secondary" data-workflow-action="return_to_draft">Zurück an Draft</button>',

            default => '<a class="tf-workflow-link" href="/admin/explorer/?workspace=draft">Draft bearbeiten</a>',
        };
    }
}