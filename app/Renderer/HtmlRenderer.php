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
        $tagline = htmlspecialchars((string)$config->get('tagline', 'Structure first. Content grows in Layers.'), ENT_QUOTES, 'UTF-8');

        $content = '';

        foreach ($page->nodes() as $node) {
            $content .= $this->renderNode($node);
        }

        return <<<HTML
<!doctype html>F
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