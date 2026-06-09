<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 013
 * TextNode Editing
 *
 * - ergänzt eine einfache API zum Speichern von TextNode content im Draft Workspace
 * - erweitert den Explorer Inspector um ein TextNode-Editorfeld
 * - speichert grundsätzlich nur in draft
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 013 TextNode Editing gestartet');

    $write($root . '/app/Core/PageEditor.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class PageEditor
{
    public static function updateTextNodeContent(array &$pageData, string $nodeId, string $content): bool
    {
        foreach ($pageData['children'] ?? [] as &$node) {
            if (!is_array($node)) {
                continue;
            }

            if (self::updateTextNodeRecursive($node, $nodeId, $content)) {
                return true;
            }
        }

        return false;
    }

    protected static function updateTextNodeRecursive(array &$node, string $nodeId, string $content): bool
    {
        if (($node['id'] ?? '') === $nodeId) {
            if (($node['type'] ?? '') !== 'text') {
                throw new RuntimeException("Node is not editable as text: {$nodeId}");
            }

            $node['content'] = $content;
            $node['updated_at'] = date('c');

            return true;
        }

        foreach ($node['children'] ?? [] as &$child) {
            if (is_array($child) && self::updateTextNodeRecursive($child, $nodeId, $content)) {
                return true;
            }
        }

        return false;
    }
}
PHP);

    $write($root . '/public/api/node/save-text.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Core\PageEditor;
use TreeForge\Core\Workspace;

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Only POST allowed');
    }

    $root = dirname(__DIR__, 3);

    $payload = json_decode((string)file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        throw new RuntimeException('Invalid JSON payload');
    }

    $pageId = (string)($payload['page'] ?? 'home');
    $nodeId = (string)($payload['node'] ?? '');
    $content = (string)($payload['content'] ?? '');

    if ($nodeId === '') {
        throw new RuntimeException('Missing node id');
    }

    // Sicherheit: Bearbeitung geht vorerst immer nur in draft.
    $workspace = Workspace::draft($root);
    $workspace->ensurePage($pageId);

    $file = $workspace->pagePath($pageId);
    $pageData = json_decode((string)file_get_contents($file), true);

    if (!is_array($pageData)) {
        throw new RuntimeException('Invalid page JSON');
    }

    $updated = PageEditor::updateTextNodeContent($pageData, $nodeId, $content);

    if (!$updated) {
        throw new RuntimeException("Node not found: {$nodeId}");
    }

    $workspace->savePage($pageId, $pageData);

    echo json_encode([
        'ok' => true,
        'message' => 'TextNode im Draft gespeichert.',
        'workspace' => 'draft',
        'page' => $pageId,
        'node' => $nodeId,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
PHP);

    $write($root . '/app/Modules/Explorer/ExplorerRenderer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Explorer;

use TreeForge\Core\NodeInspector;

class ExplorerRenderer
{
    public function render(array $pageData, string $workspace, array $workspaceStats, ?string $notice = null): string
    {
        $tree = (new ExplorerTree())->renderPageTree($pageData);
        $workspace = htmlspecialchars($workspace, ENT_QUOTES, 'UTF-8');

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

            $workspaceLinks .= '<a class="tf-workspace-link' . $active . '" href="/explorer?workspace=' . $key . '">';
            $workspaceLinks .= '<span class="tf-dot"></span>';
            $workspaceLinks .= '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
            $workspaceLinks .= '<span class="tf-count">' . $count . '</span>';
            $workspaceLinks .= '</a>';
        }

        $pageJson = htmlspecialchars(
            json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            ENT_QUOTES,
            'UTF-8'
        );

        $nodeCount = NodeInspector::countNodes($pageData);
        $pageTitle = htmlspecialchars((string)($pageData['title'] ?? 'Page'), ENT_QUOTES, 'UTF-8');

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
      <p>Structure first. Content grows in Layers.</p>
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
    </aside>

    <section class="tf-panel tf-tree-panel">
      <div class="tf-panel-head">
        <h2>Tree</h2>
        <span class="tf-badge">{$workspace}</span>
      </div>

      {$tree}

      <footer class="tf-panel-footer">
        <span>Page: {$pageTitle}</span>
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
            <dd>{$workspace}</dd>
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

        <section class="tf-inspector-section" id="tfPreviewSection" hidden>
          <h3>Preview</h3>
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
      workspace: "{$workspace}",
      page: "home"
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1/prism.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1/components/prism-css.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1/components/prism-markdown.min.js"></script>
  <script src="/assets/js/explorer.js"></script>
</body>
</html>
HTML;
    }
}
PHP);

    $write($root . '/public/assets/js/explorer.js', <<<'JS'
(function () {
  const buttons = document.querySelectorAll('.tf-tree-node-button');
  const empty = document.getElementById('tfInspectorEmpty');
  const content = document.getElementById('tfInspectorContent');
  const idTarget = document.getElementById('tfInspectorId');
  const typeTarget = document.getElementById('tfInspectorType');
  const childrenTarget = document.getElementById('tfInspectorChildren');
  const jsonTarget = document.getElementById('tfInspectorJson');
  const propertiesTarget = document.getElementById('tfInspectorProperties');
  const previewSection = document.getElementById('tfPreviewSection');
  const previewCode = document.getElementById('tfPreviewCode');
  const textEditorSection = document.getElementById('tfTextEditorSection');
  const textEditor = document.getElementById('tfTextEditor');
  const saveButton = document.getElementById('tfSaveTextNode');
  const saveStatus = document.getElementById('tfSaveStatus');
  const inspectorMode = document.getElementById('tfInspectorMode');

  let selectedNode = null;

  function valueToString(value) {
    if (value === null) return 'null';
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
  }

  function renderProperties(properties) {
    propertiesTarget.innerHTML = '';
    const keys = Object.keys(properties || {});

    if (keys.length === 0) {
      const emptyRow = document.createElement('div');
      emptyRow.className = 'tf-property-empty';
      emptyRow.textContent = 'Keine Properties vorhanden.';
      propertiesTarget.appendChild(emptyRow);
      return;
    }

    keys.forEach((key) => {
      const row = document.createElement('div');
      row.className = 'tf-property-row';

      const name = document.createElement('div');
      name.className = 'tf-property-name';
      name.textContent = key;

      const value = document.createElement('pre');
      value.className = 'tf-property-value';
      value.textContent = valueToString(properties[key]);

      row.appendChild(name);
      row.appendChild(value);
      propertiesTarget.appendChild(row);
    });
  }

  function renderPreview(preview) {
    if (!preview || preview.kind !== 'code') {
      previewSection.hidden = true;
      previewCode.textContent = '';
      previewCode.className = '';
      return;
    }

    const lang = preview.language || 'markup';
    previewCode.textContent = preview.content || '';
    previewCode.className = 'language-' + lang;
    previewSection.hidden = false;

    if (window.Prism) {
      Prism.highlightElement(previewCode);
    }
  }

  function renderTextEditor(data) {
    const isText = data && data.type === 'text';
    const workspace = (window.TreeForgeExplorer && window.TreeForgeExplorer.workspace) || 'published';

    if (!isText) {
      textEditorSection.hidden = true;
      inspectorMode.textContent = 'readonly';
      return;
    }

    textEditor.value = (data.properties && data.properties.content) ? data.properties.content : '';
    textEditorSection.hidden = false;

    if (workspace === 'draft') {
      saveButton.disabled = false;
      inspectorMode.textContent = 'editable';
      saveStatus.textContent = '';
    } else {
      saveButton.disabled = true;
      inspectorMode.textContent = 'readonly';
      saveStatus.textContent = 'Zum Bearbeiten Draft Workspace öffnen.';
    }
  }

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      buttons.forEach((item) => item.classList.remove('active'));
      button.classList.add('active');

      const raw = button.getAttribute('data-node-json') || '{}';
      let data;

      try {
        data = JSON.parse(raw);
      } catch (error) {
        data = { id: '–', type: 'unknown', properties: {}, preview: {}, children_count: 0, raw: raw };
      }

      selectedNode = data;

      idTarget.textContent = data.id || '–';
      typeTarget.textContent = data.type || 'unknown';
      childrenTarget.textContent = data.children_count ?? 0;

      renderTextEditor(data);
      renderPreview(data.preview || {});
      renderProperties(data.properties || {});
      jsonTarget.textContent = JSON.stringify(data.raw || data, null, 2);

      empty.hidden = true;
      content.hidden = false;
    });
  });

  if (saveButton) {
    saveButton.addEventListener('click', async () => {
      if (!selectedNode || selectedNode.type !== 'text') {
        return;
      }

      saveStatus.textContent = 'Speichere ...';

      try {
        const response = await fetch('/api/node/save-text.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            page: (window.TreeForgeExplorer && window.TreeForgeExplorer.page) || 'home',
            node: selectedNode.id,
            content: textEditor.value
          })
        });

        const result = await response.json();

        if (!result.ok) {
          throw new Error(result.error || 'Fehler beim Speichern');
        }

        saveStatus.textContent = 'Gespeichert. Seite wird neu geladen ...';

        setTimeout(() => {
          window.location.href = '/explorer?workspace=draft';
        }, 650);

      } catch (error) {
        saveStatus.textContent = error.message;
      }
    });
  }
})();
JS);

    $write($root . '/public/assets/css/explorer.css', <<<'CSS'
:root {
  --tf-green: #1E3D1C;
  --tf-gold:  #D88A22;
  --tf-dark:  #121A17;
  --tf-light: #F5F3EA;
  --tf-cream: #FFFAF0;
  --tf-border: rgba(23, 63, 53, .16);
}

* { box-sizing: border-box; }

body {
  margin: 0;
  min-height: 100vh;
  background: var(--tf-light);
  color: var(--tf-dark);
  font-family: var(--tf-font-ui, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
}

.tf-notice {
  margin: 1rem 1rem 0;
  padding: .85rem 1rem;
  border-radius: .9rem;
  background: rgba(216, 138, 34, .16);
  color: #7a4b0f;
  border: 1px solid rgba(216, 138, 34, .25);
  font-weight: 700;
}

.tf-explorer-header {
  min-height: 86px;
  padding: 1rem 1.5rem;
  background: rgba(255, 250, 240, .92);
  border-bottom: 1px solid var(--tf-border);
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.tf-brand-link { display: inline-flex; }

.tf-explorer-logo {
  width: 260px;
  max-width: 42vw;
  height: auto;
  display: block;
}

.tf-explorer-header h1 {
  margin: 0;
  font-size: 1.35rem;
  color: var(--tf-green);
}

.tf-explorer-header p {
  margin: .15rem 0 0;
  color: #6b746f;
}

.tf-explorer-shell {
  display: grid;
  grid-template-columns: 240px minmax(300px, 1fr) minmax(420px, .95fr);
  gap: 1rem;
  padding: 1rem;
}

.tf-panel {
  background: var(--tf-cream);
  border: 1px solid var(--tf-border);
  border-radius: 1rem;
  padding: 1rem;
  min-height: calc(100vh - 120px);
  box-shadow: 0 1rem 2.8rem rgba(18, 26, 23, .05);
}

.tf-panel h2 {
  margin: 0 0 1rem;
  font-size: 1rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--tf-green);
}

.tf-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.tf-badge {
  display: inline-flex;
  border-radius: 999px;
  background: rgba(216, 138, 34, .16);
  color: #8a5411;
  font-size: .8rem;
  font-weight: 700;
  padding: .25rem .6rem;
}

.tf-workspace-link {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .75rem .8rem;
  border-radius: .75rem;
  text-decoration: none;
  color: var(--tf-dark);
  font-weight: 700;
  margin-bottom: .35rem;
}

.tf-workspace-link:hover,
.tf-workspace-link.active {
  background: rgba(30, 61, 28, .1);
  color: var(--tf-green);
}

.tf-dot {
  width: .7rem;
  height: .7rem;
  border-radius: 50%;
  background: var(--tf-gold);
}

.tf-count {
  margin-left: auto;
  min-width: 1.7rem;
  height: 1.7rem;
  border-radius: 999px;
  background: rgba(30, 61, 28, .1);
  color: var(--tf-green);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .8rem;
}

.tf-workspace-note {
  margin-top: 1.25rem;
  padding: .9rem;
  border-radius: .85rem;
  background: #fff;
  color: #5f6b65;
  font-size: .9rem;
  line-height: 1.6;
}

.tf-explorer-tree,
.tf-explorer-tree ul {
  list-style: none;
  padding-left: 1rem;
  margin: 0;
}

.tf-explorer-tree { padding-left: 0; }

.tf-tree-page > .tf-tree-label {
  display: inline-flex;
  align-items: center;
  font-weight: 800;
  color: var(--tf-green);
  margin-bottom: .65rem;
}

.tf-tree-node { margin: .35rem 0; }

.tf-tree-node-button {
  width: 100%;
  border: 1px solid transparent;
  background: #fff;
  color: var(--tf-dark);
  border-radius: .75rem;
  padding: .65rem .75rem;
  text-align: left;
  cursor: pointer;
  font-weight: 700;
}

.tf-tree-node-button:hover,
.tf-tree-node-button.active {
  border-color: rgba(30, 61, 28, .25);
  background: rgba(30, 61, 28, .08);
}

.tf-node-main { display: block; }

.tf-node-id {
  display: block;
  margin-top: .15rem;
  font-size: .78rem;
  font-weight: 500;
  color: #748078;
}

.tf-panel-footer {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--tf-border);
  display: flex;
  gap: .75rem;
  flex-wrap: wrap;
  font-size: .9rem;
  color: #5f6b65;
}

.tf-panel-footer span {
  background: #fff;
  border-radius: 999px;
  padding: .35rem .7rem;
}

.tf-inspector-empty {
  padding: 2rem;
  border-radius: 1rem;
  background: #fff;
  color: #6b746f;
  text-align: center;
}

.tf-inspector-section { margin-bottom: 1rem; }

.tf-inspector-section h3 {
  margin: 0 0 .65rem;
  font-size: 1rem;
  color: var(--tf-green);
}

.tf-inspector dl {
  display: grid;
  grid-template-columns: 110px 1fr;
  gap: .5rem 1rem;
  background: #fff;
  border-radius: 1rem;
  padding: 1rem;
  margin: 0;
}

.tf-inspector dt {
  font-weight: 800;
  color: var(--tf-green);
}

.tf-inspector dd { margin: 0; }

.tf-editor-section {
  background: rgba(30, 61, 28, .06);
  border: 1px solid rgba(30, 61, 28, .14);
  border-radius: 1rem;
  padding: 1rem;
}

.tf-editor-hint {
  margin: -.25rem 0 .75rem;
  color: #5f6b65;
  font-size: .9rem;
}

.tf-textarea {
  width: 100%;
  resize: vertical;
  border: 1px solid rgba(23, 63, 53, .2);
  border-radius: .85rem;
  padding: .85rem;
  font: inherit;
  line-height: 1.45;
  background: #fff;
  color: var(--tf-dark);
}

.tf-editor-actions {
  margin-top: .75rem;
  display: flex;
  align-items: center;
  gap: .75rem;
  flex-wrap: wrap;
}

.tf-action-button {
  border: 0;
  border-radius: .8rem;
  background: var(--tf-green);
  color: #fff;
  font-weight: 800;
  padding: .75rem 1rem;
  cursor: pointer;
}

.tf-action-button:disabled {
  opacity: .45;
  cursor: not-allowed;
}

.tf-save-status {
  color: #5f6b65;
  font-size: .9rem;
  font-weight: 700;
}

.tf-properties {
  display: grid;
  gap: .55rem;
}

.tf-property-row {
  background: #fff;
  border-radius: .85rem;
  padding: .8rem;
  border: 1px solid rgba(23, 63, 53, .08);
}

.tf-property-name {
  color: var(--tf-green);
  font-weight: 800;
  margin-bottom: .35rem;
}

.tf-property-value {
  margin: 0;
  background: rgba(18, 26, 23, .04);
  color: var(--tf-dark);
  border-radius: .65rem;
  padding: .65rem;
}

.tf-property-empty {
  background: #fff;
  color: #6b746f;
  border-radius: .85rem;
  padding: .8rem;
}

pre {
  white-space: pre-wrap;
  overflow: auto;
  background: #0d1411;
  color: #e8f4ec;
  border-radius: 1rem;
  padding: 1rem;
  font-family: var(--tf-font-code, Consolas, monospace);
  font-size: .9rem;
}

.tf-page-json { margin-top: 1rem; }

.tf-page-json summary {
  cursor: pointer;
  font-weight: 800;
  color: var(--tf-green);
  margin-bottom: .75rem;
}

@media (max-width: 1200px) {
  .tf-explorer-shell { grid-template-columns: 1fr; }
  .tf-panel { min-height: auto; }
}
CSS);

    $write($root . '/docs/text-node-editing.md', <<<'MD'
# TextNode Editing

Patch 013 ergänzt die erste echte Bearbeitungsfunktion.

## Regel

Bearbeitet wird vorerst ausschließlich im Draft Workspace.

```text
published = live, readonly
draft     = editierbar
review    = später Freigabe
```

## API

```text
POST /api/node/save-text.php
```

Payload:

```json
{
  "page": "home",
  "node": "node_hero",
  "content": "Neuer Text"
}
```

## Ablauf

```text
Explorer
↓
TextNode anklicken
↓
Content bearbeiten
↓
In Draft speichern
↓
Draft Preview prüfen
```

## Warum wichtig?

TreeForge kann nun erstmals nicht nur anzeigen, sondern Inhalte ändern.

MD);

    $log('Patch 013 TextNode Editing fertig');
};
