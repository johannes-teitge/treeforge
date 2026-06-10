<?php
declare(strict_types=1);

namespace TreeForge\Renderer;

use League\CommonMark\CommonMarkConverter;
use TreeForge\Core\Config;
use TreeForge\Core\MarkdownRenderer;
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
    protected string $inlineCss = '';

    protected ?CommonMarkConverter $markdownConverter = null;

    public function render(Page $page, Config $config): string
    {
        $title = htmlspecialchars($page->title(), ENT_QUOTES, 'UTF-8');
        $appName = htmlspecialchars((string)$config->get('name', 'TreeForge CMS'), ENT_QUOTES, 'UTF-8');
        $tagline = htmlspecialchars((string)$config->get('tagline', 'Structure first. Content grows.'), ENT_QUOTES, 'UTF-8');

        $this->inlineCss = '';
        $content = '';

        foreach ($page->nodes() as $node) {
            $content .= $this->renderNode($node);
        }

        $styleBlock = $this->inlineCss !== ''
            ? "\n  <style>\n" . $this->inlineCss . "\n  </style>"
            : '';

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
  <link rel="stylesheet" href="/assets/css/treeforge.css">{$styleBlock}
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
            $this->collectCss($node);
            return '';
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

    protected function collectCss(CssNode $node): void
    {
        $css = trim($node->content());

        if ($css === '') {
            return;
        }

        $this->inlineCss .= "\n/* CSS Node: " . $node->get('id', 'unknown') . " */\n" . $css . "\n";
    }

    protected function renderMarkdown(MarkdownNode $node): string
    {
        $html = MarkdownRenderer::toHtml($node->content());

        return <<<HTML
<div class="tf-node tf-node-markdown">
  {$html}
</div>
HTML;
    }

    protected function markdown(): CommonMarkConverter
    {
        if ($this->markdownConverter === null) {
            $this->markdownConverter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        return $this->markdownConverter;
    }
}