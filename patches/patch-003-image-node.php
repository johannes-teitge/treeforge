<?php
declare(strict_types=1);

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

    $log('Patch 003 ImageNode gestartet');

    $write($root . '/app/Core/ImageNode.php', <<<'CODE'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

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
CODE);

    $write($root . '/app/Core/NodeFactory.php', <<<'CODE'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeFactory
{
    public static function create(array $data): Node
    {
        return match ($data['type'] ?? '') {
            'text'  => new TextNode($data),
            'image' => new ImageNode($data),

            default => throw new RuntimeException(
                'Unknown node type: ' . ($data['type'] ?? 'undefined')
            ),
        };
    }
}
CODE);

    $write($root . '/storage/pages/home.json', <<<'CODE'
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
    }
  ]
}
CODE);

    $write($root . '/public/assets/img/treeforge-demo.svg', <<<'CODE'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 220">
  <circle cx="110" cy="110" r="96" fill="#F5F3EA" stroke="#173F35" stroke-width="8"/>
  <path d="M72 145h76l-12 22H84z" fill="#121A17"/>
  <path d="M70 130h80v18H70z" fill="#121A17"/>
  <path d="M110 70v62" stroke="#121A17" stroke-width="10" stroke-linecap="round"/>
  <path d="M110 98L82 82" stroke="#121A17" stroke-width="8" stroke-linecap="round"/>
  <path d="M110 98l28-16" stroke="#121A17" stroke-width="8" stroke-linecap="round"/>
  <circle cx="110" cy="48" r="12" fill="#4F8F46"/>
  <circle cx="82" cy="74" r="12" fill="#4F8F46"/>
  <circle cx="138" cy="74" r="12" fill="#4F8F46"/>
  <circle cx="70" cy="104" r="12" fill="#4F8F46"/>
  <circle cx="150" cy="104" r="12" fill="#4F8F46"/>
</svg>
CODE);

    $log('Patch 003 ImageNode fertig');
};