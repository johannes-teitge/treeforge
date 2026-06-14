<?php
declare(strict_types=1);

namespace TreeForge\Renderer;

use League\CommonMark\CommonMarkConverter;
use TreeForge\Core\Config;
use TreeForge\Core\MarkdownRenderer;
use TreeForge\Core\Node;
use TreeForge\Core\Page;
use TreeForge\Nodes\ButtonNode;
use TreeForge\Nodes\UnknownNode;
use TreeForge\Nodes\JavaScriptNode;
use TreeForge\Nodes\HtmlNode;
use TreeForge\Nodes\ColumnNode;
use TreeForge\Nodes\ColumnsNode;
use TreeForge\Nodes\CssNode;
use TreeForge\Nodes\ImageNode;
use TreeForge\Nodes\MarkdownNode;
use TreeForge\Nodes\CodeBlockNode;
use TreeForge\Nodes\TextNode;
use TreeForge\Nodes\HeadingNode;

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
        if ($node instanceof HtmlNode) {
            return $this->renderHtml($node);
        }

        if ($node instanceof JavaScriptNode) {
            return $this->renderJavaScript($node);
        }

        if ($node instanceof UnknownNode) {
            return $this->renderUnknown($node);
        }

        if ($node instanceof HeadingNode) {
            return $this->renderHeading($node);
        }

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

        if ($node instanceof CodeBlockNode) {
            return $this->renderCodeBlock($node);
        }

        if ($node instanceof MarkdownNode) {
            return $this->renderMarkdown($node);
        }

        return '<div class="tf-node tf-node-unknown">Unknown node: '
            . htmlspecialchars($node->type(), ENT_QUOTES, 'UTF-8')
            . '</div>';
    }


    protected function renderHtml(HtmlNode $node): string
    {
        $html = $node->html();
        $id = htmlspecialchars($this->domId($node), ENT_QUOTES, 'UTF-8');

        if (trim($html) === '') {
            return '';
        }

        return <<<HTML
<div id="{$id}" class="tf-node tf-node-html">
  {$html}
</div>
HTML;
    }

    protected function renderJavaScript(JavaScriptNode $node): string
    {
        $script = trim($node->script());
        $id = htmlspecialchars($this->domId($node), ENT_QUOTES, 'UTF-8');

        if ($script === '') {
            return '';
        }

        return <<<HTML
<script id="{$id}" class="tf-node tf-node-javascript">
{$script}
</script>
HTML;
    }

    protected function renderUnknown(UnknownNode $node): string
    {
        $type = htmlspecialchars($node->originalType(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $id = htmlspecialchars($this->domId($node), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div id="{$id}" class="tf-node tf-node-unknown" data-node-type="{$type}">
  <strong>Unbekannter Node-Typ:</strong> {$type}
</div>
HTML;
    }

    protected function renderHeading(HeadingNode $node): string
    {
        $level = $node->level();
        $text = htmlspecialchars($node->text(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $id = htmlspecialchars($this->domId($node), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<{$level} id="{$id}" class="tf-node tf-node-heading tf-node-heading-{$level}">{$text}</{$level}>
HTML;
    }

    protected function renderText(TextNode $node): string
    {
        $content = $this->renderTextContentAsParagraphs($node->content());
        $id = htmlspecialchars($this->domId($node), ENT_QUOTES, 'UTF-8');

        if ($content === '') {
            return '';
        }

        return <<<HTML
<div id="{$id}" class="tf-node tf-node-text">
  {$content}
</div>
HTML;
    }

    protected function renderTextContentAsParagraphs(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));

        if ($text === '') {
            return '';
        }

        $blocks = preg_split("/\n{2,}/", $text) ?: [];
        $paragraphs = [];

        foreach ($blocks as $block) {
            $block = trim($block, "\n");

            if (trim($block) === '') {
                continue;
            }

            $escaped = htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escaped = preg_replace("/\n/", "<br>\n", $escaped) ?? $escaped;

            $paragraphs[] = '<p>' . $escaped . '</p>';
        }

        return implode("\n  ", $paragraphs);
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

    protected function renderCodeBlock(CodeBlockNode $node): string
    {
        $code = htmlspecialchars($node->code(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $language = htmlspecialchars($node->language(), ENT_QUOTES, 'UTF-8');
        $caption = htmlspecialchars($node->caption(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $id = htmlspecialchars($this->domId($node), ENT_QUOTES, 'UTF-8');

        $classes = ['tf-node', 'tf-node-codeblock', 'language-' . $language];

        if ($node->showLineNumbers()) {
            $classes[] = 'has-line-numbers';
        }

        if ($node->wrap()) {
            $classes[] = 'is-wrapped';
        }

        $class = htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8');
        $captionHtml = $caption !== '' ? '<figcaption>' . $caption . '</figcaption>' : '';

        return <<<HTML
<figure id="{$id}" class="{$class}">
  {$captionHtml}
  <pre><code class="language-{$language}">{$code}</code></pre>
</figure>
HTML;
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

    protected function domId(Node $node): string
    {
        $data = $node->data();
        $advanced = [];

        if (isset($data['properties']) && is_array($data['properties'])) {
            $advanced = $data['properties']['advanced'] ?? [];
            $advanced = is_array($advanced) ? $advanced : [];
        }

        $customId = trim((string)($advanced['css_id'] ?? $data['css_id'] ?? ''));

        if ($customId !== '') {
            return preg_replace('/[^A-Za-z0-9_-]/', '-', $customId) ?: 'tf-node';
        }

        $id = trim($node->id());
        $id = $id !== '' ? $id : 'unknown';
        $id = str_replace('_', '-', $id);
        $id = preg_replace('/[^A-Za-z0-9_-]/', '-', $id) ?: 'unknown';

        return 'tf-' . $id;
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