<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 010
 * Inspector Pro
 *
 * - führt NodeInspector ein
 * - erweitert Explorer um Property Inspector
 * - zählt Nodes pro Workspace
 * - ergänzt Explorer-Statistik
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

    $log('Patch 010 Inspector Pro gestartet');

    $write($root . '/app/Core/NodeInspector.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

class NodeInspector
{
    public static function inspectArray(array $node): array
    {
        $properties = [];

        foreach ($node as $key => $value) {
            if (in_array($key, ['id', 'type', 'children'], true)) {
                continue;
            }

            $properties[$key] = $value;
        }

        return [
            'id' => (string)($node['id'] ?? ''),
            'type' => (string)($node['type'] ?? 'unknown'),
            'properties' => $properties,
            'has_children' => isset($node['children']) && is_array($node['children']) && $node['children'] !== [],
            'children_count' => isset($node['children']) && is_array($node['children']) ? count($node['children']) : 0,
            'raw' => $node,
        ];
    }

    public static function countNodes(array $pageOrNode): int
    {
        $count = 0;

        foreach ($pageOrNode['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }

            $count++;
            $count += self::countNodes($child);
        }

        return $count;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'text' => 'Text',
            'image' => 'Image',
            'button' => 'Button',
            'columns' => 'Columns',
            default => ucfirst($type),
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'text' => '📝',
            'image' => '🖼',
            'button' => '🔘',
            'columns' => '📑',
            'css' => '🎨',
            default => '📦',
        };
    }
}
PHP);

    $write($root . '/app/Modules/Explorer/ExplorerTree.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Explorer;

use TreeForge\Core\NodeInspector;

class ExplorerTree
{
    public function renderPageTree(array $pageData): string
    {
        $title = htmlspecialchars((string)($pageData['title'] ?? $pageData['id'] ?? 'Page'), ENT_QUOTES, 'UTF-8');

        $html = '<ul class="tf-explorer-tree">';
        $html .= '<li class="tf-tree-page"><span class="tf-tree-label">🌳 ' . $title . '</span>';

        $children = $pageData['children'] ?? [];

        if (is_array($children) && $children !== []) {
            $html .= '<ul>';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= $this->renderNode($child);
                }
            }
            $html .= '</ul>';
        }

        $html .= '</li></ul>';

        return $html;
    }

    protected function renderNode(array $node): string
    {
        $id = htmlspecialchars((string)($node['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type = (string)($node['type'] ?? 'unknown');
        $icon = NodeInspector::typeIcon($type);
        $label = htmlspecialchars(NodeInspector::typeLabel($type), ENT_QUOTES, 'UTF-8');
        $inspect = NodeInspector::inspectArray($node);

        $json = htmlspecialchars(
            json_encode($inspect, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            ENT_QUOTES,
            'UTF-8'
        );

        $html = '<li class="tf-tree-node">';
        $html .= '<button class="tf-tree-node-button" type="button" data-node-json="' . $json . '">';
        $html .= '<span class="tf-node-main">' . $icon . ' ' . $label . '</span>';
        $html .= '<span class="tf-node-id">' . $id . '</span>';
        $html .= '</button>';

        $children = $node['children'] ?? [];

        if (is_array($children) && $children !== []) {
            $html .= '<ul>';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= $this->renderNode($child);
                }
            }
            $html .= '</ul>';
        }

        $html .= '</li>';

        return $html;
    }
}
PHP);

    $write($root . '/app/Modules/Explorer/ExplorerController.php', <<<'PHP'
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
PHP);

    $write($root . '/app/Modules/Explorer/ExplorerRenderer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Explorer;

use TreeForge\Core\NodeInspector;

class ExplorerRenderer
{
    public function render(array $pageData, string $workspace, array $workspaceStats): string
    {
        $tree = (new ExplorerTree())->renderPageTree($pageData);
        $workspace = htmlspecialchars($workspace, ENT_QUOTES, 'UTF-8');

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

  <main class="tf-explorer-shell">
    <aside class="tf-panel tf-workspaces">
      <h2>Workspaces</h2>
      {$workspaceLinks}

      <div class="tf-workspace-note">
        <strong>Live:</strong> published<br>
        <strong>Preview:</strong> draft / review
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
        <span class="tf-badge">readonly</span>
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

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      buttons.forEach((item) => item.classList.remove('active'));
      button.classList.add('active');

      const raw = button.getAttribute('data-node-json') || '{}';
      let data;

      try {
        data = JSON.parse(raw);
      } catch (error) {
        data = { id: '–', type: 'unknown', properties: {}, children_count: 0, raw: raw };
      }

      idTarget.textContent = data.id || '–';
      typeTarget.textContent = data.type || 'unknown';
      childrenTarget.textContent = data.children_count ?? 0;

      renderProperties(data.properties || {});
      jsonTarget.textContent = JSON.stringify(data.raw || data, null, 2);

      empty.hidden = true;
      content.hidden = false;
    });
  });
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
  grid-template-columns: 240px minmax(300px, 1fr) minmax(380px, .9fr);
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

    $write($root . '/docs/inspector.md', <<<'MD'
# TreeForge Inspector

Der Inspector zeigt Informationen über eine ausgewählte Node.

## Funktionen

- Node ID
- Node Type
- Workspace
- Anzahl Kinder
- Properties
- Raw JSON

## NodeInspector

Die zentrale Klasse ist:

```text
app/Core/NodeInspector.php
```

Sie analysiert Node-Arrays und liefert strukturierte Metadaten.

## Warum wichtig?

Der Inspector ist die Grundlage für:

- readonly Explorer
- späterer Property Editor
- Headless API
- Import/Export-Validierung
- Node Debugging

## Aktueller Stand

Der Inspector ist readonly.

Bearbeitung folgt später mit:

```text
Patch 011 - TextNode Editing
```

MD);

    $log('Patch 010 Inspector Pro fertig');
};
