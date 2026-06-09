<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 006
 * Node Registry Foundation
 *
 * Ziel:
 * - NodeRegistry einführen
 * - NodeFactory auf Registry umstellen
 * - TextNode und ImageNode in neuen Namespace TreeForge\Nodes verschieben/kopieren
 * - ButtonNode und ColumnsNode als vorbereitete Node-Typen ergänzen
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

    $log('Patch 006 Node Registry gestartet');

    $write($root . '/app/Core/NodeRegistry.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeRegistry
{
    protected static array $nodes = [];

    public static function register(string $type, string $class): void
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Node class not found: {$class}");
        }

        if (!is_subclass_of($class, Node::class)) {
            throw new RuntimeException("Node class must extend Node: {$class}");
        }

        self::$nodes[$type] = $class;
    }

    public static function resolve(string $type): ?string
    {
        return self::$nodes[$type] ?? null;
    }

    public static function has(string $type): bool
    {
        return isset(self::$nodes[$type]);
    }

    public static function all(): array
    {
        return self::$nodes;
    }
}
PHP);

    $write($root . '/app/Nodes/TextNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class TextNode extends Node
{
    public function content(): string
    {
        return (string)($this->data['content'] ?? '');
    }
}
PHP);

    $write($root . '/app/Nodes/ImageNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class ImageNode extends Node
{
    public function src(): string
    {
        return (string)($this->data['src'] ?? '');
    }

    public function alt(): string
    {
        return (string)($this->data['alt'] ?? '');
    }

    public function caption(): string
    {
        return (string)($this->data['caption'] ?? '');
    }
}
PHP);

    $write($root . '/app/Nodes/ButtonNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class ButtonNode extends Node
{
    public function label(): string
    {
        return (string)($this->data['label'] ?? 'Button');
    }

    public function url(): string
    {
        return (string)($this->data['url'] ?? '#');
    }

    public function variant(): string
    {
        return (string)($this->data['variant'] ?? 'primary');
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

    $write($root . '/app/Core/NodeFactory.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeFactory
{
    public static function create(array $data): Node
    {
        $type = (string)($data['type'] ?? 'unknown');
        $class = NodeRegistry::resolve($type);

        if (!$class) {
            throw new RuntimeException("Unknown node type: {$type}");
        }

        return new $class($data);
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

NodeRegistry::register('text', TextNode::class);
NodeRegistry::register('image', ImageNode::class);
NodeRegistry::register('button', ButtonNode::class);
NodeRegistry::register('columns', ColumnsNode::class);
PHP);

    $write($root . '/public/index.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/bootstrap.php';

use TreeForge\Core\Config;
use TreeForge\Core\Page;
use TreeForge\Renderer\HtmlRenderer;

$root = dirname(__DIR__);

$config = new Config($root . '/storage/config/app.json');
$page = new Page($root . '/storage/pages/home.json');

$renderer = new HtmlRenderer();

echo $renderer->render($page, $config);
PHP);

    $write($root . '/app/Renderer/HtmlRenderer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Renderer;

use TreeForge\Core\Config;
use TreeForge\Core\Node;
use TreeForge\Core\Page;
use TreeForge\Nodes\ButtonNode;
use TreeForge\Nodes\ColumnsNode;
use TreeForge\Nodes\ImageNode;
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

        return '<div class="tf-node tf-node-unknown">Unknown node: '
            . htmlspecialchars($node->type(), ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    protected function renderText(TextNode $node): string
    {
        $content = htmlspecialchars($node->content(), ENT_QUOTES, 'UTF-8');

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
        $content = '';

        foreach ($node->children() as $childNode) {
            $content .= '<div class="tf-column">' . $this->renderNode($childNode) . '</div>';
        }

        return <<<HTML
<div class="tf-node tf-node-columns">
  {$content}
</div>
HTML;
    }
}
PHP);

    $write($root . '/storage/pages/home.json', <<<'JSON'
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
    }
  ]
}
JSON);

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
  justify-content:center;
  text-align:center;
  padding:2rem;
}

.tf-brand-logo{
  width:min(520px, 90vw);
  height:auto;
  margin-bottom:1.5rem;
}

.tf-content{
  margin-top:2rem;
  max-width:760px;
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
  grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:1rem;
}

.tf-column .tf-node{
  margin:0;
}

.tf-node-unknown{
  color:#9b1c1c;
}
CSS);

    $write($root . '/docs/node-registry.md', <<<'MD'
# TreeForge Node Registry

Die Node Registry ist das zentrale Verzeichnis aller bekannten Node-Typen.

## Ziel

Statt die NodeFactory fest mit einzelnen Klassen zu verdrahten, werden Node-Typen registriert.

```php
NodeRegistry::register('text', TextNode::class);
NodeRegistry::register('image', ImageNode::class);
NodeRegistry::register('button', ButtonNode::class);
NodeRegistry::register('columns', ColumnsNode::class);
```

## Ablauf

```text
JSON
  ↓
NodeRegistry
  ↓
NodeFactory
  ↓
Node-Objekt
  ↓
Renderer
```

## Vorteil

Die Registry ist später Grundlage für:

- Editor-Blockauswahl
- Property Panels
- Modul-System
- Import/Export
- Headless API
- Custom Nodes

## Aktuelle Node-Typen

```text
text
image
button
columns
```

MD);

    $log('Patch 006 Node Registry fertig');
};
