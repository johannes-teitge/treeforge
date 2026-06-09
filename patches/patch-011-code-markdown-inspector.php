<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 011
 * Code + Markdown Inspector
 *
 * - ergänzt InspectorPreviewRenderer
 * - CSS/Markdown Content Preview mit Prism.js CDN
 * - registriert CssNode und MarkdownNode
 * - ergänzt Demo-Nodes im Draft Workspace
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

    $log('Patch 011 Code + Markdown Inspector gestartet');

    $write($root . '/app/Nodes/CssNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class CssNode extends Node
{
    public function content(): string
    {
        return (string)($this->data['content'] ?? '');
    }
}
PHP);

    $write($root . '/app/Nodes/MarkdownNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class MarkdownNode extends Node
{
    public function content(): string
    {
        return (string)($this->data['content'] ?? '');
    }
}
PHP);

    $write($root . '/app/Core/bootstrap.php', <<<'PHP'
<?php
declare(strict_types=1);

use TreeForge\Core\NodeRegistry;
use TreeForge\Nodes\TextNode;
use TreeForge\Nodes\ImageNode;
use TreeForge\Nodes\ButtonNode;
use TreeForge\Nodes\ColumnsNode;
use TreeForge\Nodes\CssNode;
use TreeForge\Nodes\MarkdownNode;

NodeRegistry::register('text', TextNode::class);
NodeRegistry::register('image', ImageNode::class);
NodeRegistry::register('button', ButtonNode::class);
NodeRegistry::register('columns', ColumnsNode::class);
NodeRegistry::register('css', CssNode::class);
NodeRegistry::register('markdown', MarkdownNode::class);
PHP);

    $write($root . '/app/Core/InspectorPreviewRenderer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

class InspectorPreviewRenderer
{
    public static function render(array $node): array
    {
        $type = (string)($node['type'] ?? 'unknown');

        return match ($type) {
            'css' => self::codePreview($node, 'css'),
            'markdown' => self::codePreview($node, 'markdown'),
            default => [
                'kind' => 'none',
                'language' => '',
                'content' => '',
            ],
        };
    }

    protected static function codePreview(array $node, string $language): array
    {
        return [
            'kind' => 'code',
            'language' => $language,
            'content' => (string)($node['content'] ?? ''),
        ];
    }
}
PHP);

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
            'preview' => InspectorPreviewRenderer::render($node),
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
            'css' => 'CSS',
            'markdown' => 'Markdown',
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
            'markdown' => '⬇️',
            default => '📦',
        };
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

      idTarget.textContent = data.id || '–';
      typeTarget.textContent = data.type || 'unknown';
      childrenTarget.textContent = data.children_count ?? 0;

      renderPreview(data.preview || {});
      renderProperties(data.properties || {});
      jsonTarget.textContent = JSON.stringify(data.raw || data, null, 2);

      empty.hidden = true;
      content.hidden = false;
    });
  });
})();
JS);

    $write($root . '/storage/workspaces/draft/pages/home.json', <<<'JSON'
{
  "id": "home",
  "type": "page",
  "title": "Startseite",
  "children": [
    {
      "id": "node_hero",
      "type": "text",
      "content": "Welcome to TreeForge CMS"
    },
    {
      "id": "node_image_demo",
      "type": "image",
      "src": "/assets/img/treeforge-demo.svg",
      "alt": "TreeForge Demo Icon",
      "caption": "ImageNode rendered from JSON."
    },
    {
      "id": "node_button_demo",
      "type": "button",
      "label": "TreeForge starten",
      "url": "#",
      "variant": "primary"
    },
    {
      "id": "node_css_demo",
      "type": "css",
      "content": ".tf-demo {\\n  color: #1E3D1C;\\n  border-left: 4px solid #D88A22;\\n  padding: 1rem;\\n}"
    },
    {
      "id": "node_markdown_demo",
      "type": "markdown",
      "content": "# TreeForge Markdown\\n\\nDas ist **Markdown** als eigener Node-Typ.\\n\\n- Struktur\\n- Content\\n- Layers"
    }
  ]
}
JSON);

    $write($root . '/docs/code-markdown-inspector.md', <<<'MD'
# Code & Markdown Inspector

Patch 011 erweitert den Explorer um Code- und Markdown-Vorschauen.

## Neue Node-Typen

```text
css
markdown
```

## Beispiel CSS Node

```json
{
  "id": "node_css_demo",
  "type": "css",
  "content": ".demo { color: red; }"
}
```

## Beispiel Markdown Node

```json
{
  "id": "node_markdown_demo",
  "type": "markdown",
  "content": "# Überschrift\n\n**Fett**"
}
```

## Technik

Für das Syntax Highlighting wird zunächst Prism.js per CDN eingebunden.

Später kann Prism lokal ausgeliefert werden.

## Architektur

Die Klasse

```text
app/Core/InspectorPreviewRenderer.php
```

entscheidet, wie bestimmte Node-Typen im Inspector angezeigt werden.

MD);

    $log('Patch 011 Code + Markdown Inspector fertig');
};
