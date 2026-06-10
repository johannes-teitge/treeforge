<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 021
 * Columns Tree Alpha
 *
 * Ziel:
 * - ColumnNode ergänzen
 * - columns/column im Renderer sichtbar verschachtelt ausgeben
 * - Explorer Icons/Labels für column verbessern
 * - Draft-Demo mit echter verschachtelter Columns-Struktur ergänzen
 *
 * Damit wird der Tree-Vorteil erstmals deutlich:
 * Page
 * └─ Columns
 *    ├─ Column
 *    │  ├─ Text
 *    │  └─ Image
 *    └─ Column
 *       ├─ Text
 *       └─ Button
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

    $log('Patch 021 Columns Tree Alpha gestartet');

    $write($root . '/app/Nodes/ColumnNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;
use TreeForge\Core\NodeFactory;

class ColumnNode extends Node
{
    public function width(): string
    {
        return (string)($this->data['width'] ?? 'auto');
    }

    public function children(): array
    {
        $nodes = [];

        foreach ($this->data['children'] ?? [] as $nodeData) {
            $nodes[] = NodeFactory::create($nodeData);
        }

        return $nodes;
    }
}
PHP);

    $write($root . '/app/Nodes/ColumnsNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;
use TreeForge\Core\NodeFactory;

class ColumnsNode extends Node
{
    public function count(): int
    {
        return (int)($this->data['settings']['columns'] ?? count($this->data['children'] ?? []));
    }

    public function gap(): string
    {
        return (string)($this->data['settings']['gap'] ?? '1rem');
    }

    public function children(): array
    {
        $nodes = [];

        foreach ($this->data['children'] ?? [] as $nodeData) {
            $nodes[] = NodeFactory::create($nodeData);
        }

        return $nodes;
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
use TreeForge\Nodes\ColumnNode;
use TreeForge\Nodes\CssNode;
use TreeForge\Nodes\MarkdownNode;

NodeRegistry::register('text', TextNode::class);
NodeRegistry::register('image', ImageNode::class);
NodeRegistry::register('button', ButtonNode::class);
NodeRegistry::register('columns', ColumnsNode::class);
NodeRegistry::register('column', ColumnNode::class);
NodeRegistry::register('css', CssNode::class);
NodeRegistry::register('markdown', MarkdownNode::class);
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
            'column' => 'Column',
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
            'columns' => '▦',
            'column' => '▥',
            'css' => '🎨',
            'markdown' => '⬇️',
            default => '📦',
        };
    }
}
PHP);

    $write($root . '/app/Renderer/HtmlRenderer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Renderer;

use TreeForge\Core\Config;
use TreeForge\Core\Node;
use TreeForge\Core\Page;
use TreeForge\Nodes\ButtonNode;
use TreeForge\Nodes\ColumnNode;
use TreeForge\Nodes\ColumnsNode;
use TreeForge\Nodes\CssNode;
use TreeForge\Nodes\ImageNode;
use TreeForge\Nodes\MarkdownNode;
use TreeForge\Nodes\TextNode;

class HtmlRenderer
{
    public function render(Page $page, Config $config): string
    {
        $title = htmlspecialchars($page->title(), ENT_QUOTES, 'UTF-8');
        $appName = htmlspecialchars((string)$config->get('name', 'TreeForge CMS'), ENT_QUOTES, 'UTF-8');
        $tagline = htmlspecialchars((string)$config->get('tagline', 'Structure first. Content grows.'), ENT_QUOTES, 'UTF-8');

        $content = '';

        foreach ($page->nodes() as $node) {
            $content .= $this->renderNode($node);
        }

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} · {$appName}</title>
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/svg+xml" href="/assets/brand/treeforge-icon.svg">
  <link rel="stylesheet" href="/assets/css/brand.css">
  <link rel="stylesheet" href="/assets/css/treeforge.css">
</head>
<body>
  <main class="tf-start">
    <img src="/assets/brand/treeforge-logo.svg" alt="TreeForge" class="tf-brand-logo">
    <p>{$tagline}</p>
    <section class="tf-content">
      {$content}
    </section>
  </main>
</body>
</html>
HTML;
    }

    protected function renderNode(Node $node): string
    {
        if ($node instanceof TextNode) {
            return $this->renderText($node);
        }

        if ($node instanceof ImageNode) {
            return $this->renderImage($node);
        }

        if ($node instanceof ButtonNode) {
            return $this->renderButton($node);
        }

        if ($node instanceof ColumnsNode) {
            return $this->renderColumns($node);
        }

        if ($node instanceof ColumnNode) {
            return $this->renderColumn($node);
        }

        if ($node instanceof CssNode) {
            return $this->renderCss($node);
        }

        if ($node instanceof MarkdownNode) {
            return $this->renderMarkdown($node);
        }

        return '<div class="tf-node tf-node-unknown">Unknown node: '
            . htmlspecialchars($node->type(), ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    protected function renderText(TextNode $node): string
    {
        $content = nl2br(htmlspecialchars($node->content(), ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<div class="tf-node tf-node-text">
  {$content}
</div>
HTML;
    }

    protected function renderImage(ImageNode $node): string
    {
        $src = htmlspecialchars($node->src(), ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars($node->alt(), ENT_QUOTES, 'UTF-8');
        $caption = htmlspecialchars($node->caption(), ENT_QUOTES, 'UTF-8');

        $captionHtml = $caption !== ''
            ? '<figcaption>' . $caption . '</figcaption>'
            : '';

        return <<<HTML
<figure class="tf-node tf-node-image">
  <img src="{$src}" alt="{$alt}">
  {$captionHtml}
</figure>
HTML;
    }

    protected function renderButton(ButtonNode $node): string
    {
        $label = htmlspecialchars($node->label(), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($node->url(), ENT_QUOTES, 'UTF-8');
        $variant = htmlspecialchars($node->variant(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="tf-node tf-node-button">
  <a href="{$url}" class="tf-button tf-button-{$variant}">{$label}</a>
</div>
HTML;
    }

    protected function renderColumns(ColumnsNode $node): string
    {
        $count = max(1, $node->count());
        $gap = htmlspecialchars($node->gap(), ENT_QUOTES, 'UTF-8');

        $content = '';

        foreach ($node->children() as $childNode) {
            $content .= $this->renderNode($childNode);
        }

        return <<<HTML
<div class="tf-node tf-node-columns" style="--tf-columns: {$count}; --tf-column-gap: {$gap};">
  {$content}
</div>
HTML;
    }

    protected function renderColumn(ColumnNode $node): string
    {
        $content = '';

        foreach ($node->children() as $childNode) {
            $content .= $this->renderNode($childNode);
        }

        return <<<HTML
<div class="tf-column">
  {$content}
</div>
HTML;
    }

    protected function renderCss(CssNode $node): string
    {
        $content = htmlspecialchars($node->content(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<pre class="tf-node tf-node-code"><code>{$content}</code></pre>
HTML;
    }

    protected function renderMarkdown(MarkdownNode $node): string
    {
        $content = nl2br(htmlspecialchars($node->content(), ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<div class="tf-node tf-node-markdown">
  {$content}
</div>
HTML;
    }
}
PHP);

    $write($root . '/public/assets/css/treeforge.css', <<<'CSS'
:root{
  --tf-green:#1E3D1C;
  --tf-leaf:#4F8F46;
  --tf-gold:#D88A22;
  --tf-dark:#121A17;
  --tf-light:#F5F3EA;
}

body{
  margin:0;
  font-family:var(--tf-font-ui, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
  background:var(--tf-light);
  color:var(--tf-dark);
}

.tf-start{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:flex-start;
  text-align:center;
  padding:2rem;
}

.tf-brand-logo{
  width:min(520px, 90vw);
  height:auto;
  margin:1rem 0 1.5rem;
}

.tf-content{
  margin-top:2rem;
  max-width:980px;
  width:100%;
}

p{
  font-size:1.4rem;
  color:var(--tf-gold);
}

.tf-node{
  background:#fffaf0;
  border:1px solid rgba(23,63,53,.15);
  border-radius:1rem;
  padding:1rem 1.25rem;
  margin:.75rem 0;
}

.tf-node-text{
  font-size:1.1rem;
}

.tf-node-image img{
  max-width:180px;
  height:auto;
  display:block;
  margin:0 auto;
}

.tf-node-image figcaption{
  margin-top:.75rem;
  font-size:.95rem;
  color:#5f6b65;
}

.tf-node-button{
  background:transparent;
  border:0;
}

.tf-button{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:.8rem 1.25rem;
  border-radius:.8rem;
  text-decoration:none;
  font-weight:700;
}

.tf-button-primary{
  background:var(--tf-green);
  color:#fff;
}

.tf-node-columns{
  display:grid;
  grid-template-columns:repeat(var(--tf-columns, 2), minmax(0, 1fr));
  gap:var(--tf-column-gap, 1rem);
  background:rgba(30,61,28,.06);
  border:1px dashed rgba(30,61,28,.28);
}

.tf-column{
  background:#fff;
  border:1px solid rgba(30,61,28,.14);
  border-radius:1rem;
  padding:1rem;
  min-width:0;
}

.tf-column .tf-node{
  margin:.5rem 0;
}

.tf-column .tf-node:first-child{
  margin-top:0;
}

.tf-column .tf-node:last-child{
  margin-bottom:0;
}

.tf-node-code{
  text-align:left;
  white-space:pre-wrap;
  background:#0d1411;
  color:#e8f4ec;
  font-family:var(--tf-font-code, Consolas, monospace);
}

.tf-node-markdown{
  text-align:left;
}

.tf-node-unknown{
  color:#9b1c1c;
}

@media (max-width: 760px){
  .tf-node-columns{
    grid-template-columns:1fr;
  }
}
CSS);

    $draftFile = $root . '/storage/workspaces/draft/pages/home.json';

    if (file_exists($draftFile)) {
        $data = json_decode((string)file_get_contents($draftFile), true);

        if (is_array($data)) {
            $children = $data['children'] ?? [];
            $exists = false;

            foreach ($children as $child) {
                if (is_array($child) && ($child['id'] ?? '') === 'node_columns_demo') {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $children[] = [
                    'id' => 'node_columns_demo',
                    'type' => 'columns',
                    'settings' => [
                        'columns' => 2,
                        'gap' => '1rem',
                    ],
                    'children' => [
                        [
                            'id' => 'node_column_1',
                            'type' => 'column',
                            'width' => '1fr',
                            'children' => [
                                [
                                    'id' => 'node_column_1_text',
                                    'type' => 'text',
                                    'content' => "Linke Spalte\n\nHier sieht man erstmals, dass TreeForge Inhalte als echten Baum denkt."
                                ],
                                [
                                    'id' => 'node_column_1_image',
                                    'type' => 'image',
                                    'src' => '/assets/img/treeforge-demo.svg',
                                    'alt' => 'TreeForge Demo',
                                    'caption' => 'Bild innerhalb einer Column.'
                                ]
                            ]
                        ],
                        [
                            'id' => 'node_column_2',
                            'type' => 'column',
                            'width' => '1fr',
                            'children' => [
                                [
                                    'id' => 'node_column_2_text',
                                    'type' => 'text',
                                    'content' => "Rechte Spalte\n\nNodes können beliebig verschachtelt werden."
                                ],
                                [
                                    'id' => 'node_column_2_button',
                                    'type' => 'button',
                                    'label' => 'Mehr Struktur',
                                    'url' => '#',
                                    'variant' => 'primary'
                                ]
                            ]
                        ]
                    ]
                ];

                $data['children'] = $children;

                file_put_contents(
                    $draftFile,
                    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );

                $log("Columns Demo in Draft ergänzt: {$draftFile}");
            } else {
                $log("Columns Demo bereits vorhanden: {$draftFile}");
            }
        }
    } else {
        $log("Draft home.json fehlt, Columns Demo übersprungen.");
    }

    $write($root . '/docs/columns-tree-alpha.md', <<<'MD'
# Columns Tree Alpha

Patch 021 ergänzt eine echte verschachtelte Baumstruktur.

## Neue Node-Typen

```text
columns
column
```

## Beispiel

```text
Page
└─ Columns
   ├─ Column
   │  ├─ Text
   │  └─ Image
   └─ Column
      ├─ Text
      └─ Button
```

## Warum wichtig?

Bis hier war TreeForge zwar technisch ein Tree-System, aber die Demo war noch relativ flach.

Mit Columns sieht man erstmals den Unterschied zu klassischen Pagebuildern:

```text
Content ist nicht eine Liste von Blöcken.
Content ist ein strukturierter Baum.
```

## Renderer

`ColumnsNode` rendert ein Grid.

`ColumnNode` rendert eine einzelne Spalte und deren Kind-Nodes.

## Explorer

Der Explorer zeigt die verschachtelte Struktur automatisch, weil er rekursiv arbeitet.

MD);

    $log('Patch 021 Columns Tree Alpha fertig');
};
