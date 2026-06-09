<?php
declare(strict_types=1);

namespace TreeForge\Renderer;

use TreeForge\Core\Config;
use TreeForge\Core\Page;

class HtmlRenderer
{
    public function render(Page $page, Config $config): string
    {
        $title = htmlspecialchars($page->title(), ENT_QUOTES, 'UTF-8');
        $appName = htmlspecialchars((string)$config->get('name', 'TreeForge CMS'), ENT_QUOTES, 'UTF-8');
        $tagline = htmlspecialchars((string)$config->get('tagline', 'Structure first. Content grows.'), ENT_QUOTES, 'UTF-8');

        $content = '';

        foreach ($page->children() as $node) {
            $content .= $this->renderNode($node);
        }

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} · {$appName}</title>
  <link rel="stylesheet" href="/assets/css/treeforge.css">
</head>
<body>
  <main class="tf-start">
    <div class="tf-logo">🌳⚒️</div>
    <h1>{$appName}</h1>
    <p>{$tagline}</p>
    <section class="tf-content">
      {$content}
    </section>
  </main>
</body>
</html>
HTML;
    }

    protected function renderNode(array $node): string
    {
        $type = $node['type'] ?? 'unknown';

        return match ($type) {
            'text' => $this->renderText($node),
            default => '<div class="tf-node tf-node-unknown">Unknown node: ' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '</div>',
        };
    }

    protected function renderText(array $node): string
    {
        $content = htmlspecialchars((string)($node['content'] ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="tf-node tf-node-text">
  {$content}
</div>
HTML;
    }
}