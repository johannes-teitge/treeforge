<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 025
 * CommonMark Markdown Rendering
 *
 * Voraussetzung:
 * composer require league/commonmark
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

    $log('Patch 025 CommonMark Markdown Rendering gestartet');

    $rendererFile = $root . '/app/Renderer/HtmlRenderer.php';

    if (!file_exists($rendererFile)) {
        throw new RuntimeException('HtmlRenderer.php nicht gefunden.');
    }

    $renderer = file_get_contents($rendererFile);

    if (!str_contains($renderer, 'use League\\CommonMark\\CommonMarkConverter;')) {
        $renderer = str_replace(
            "namespace TreeForge\\Renderer;\n\n",
            "namespace TreeForge\\Renderer;\n\nuse League\\CommonMark\\CommonMarkConverter;\n",
            $renderer
        );
    }

    if (!str_contains($renderer, 'protected ?CommonMarkConverter $markdownConverter = null;')) {
        $renderer = str_replace(
            "protected string \$inlineCss = '';",
            "protected string \$inlineCss = '';\n\n    protected ?CommonMarkConverter \$markdownConverter = null;",
            $renderer
        );
    }

    $oldMarkdown = <<<'PHP_OLD'
    protected function renderMarkdown(MarkdownNode $node): string
    {
        $content = nl2br(htmlspecialchars($node->content(), ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<div class="tf-node tf-node-markdown">
  {$content}
</div>
HTML;
    }
PHP_OLD;

    $newMarkdown = <<<'PHP_NEW'
    protected function renderMarkdown(MarkdownNode $node): string
    {
        $html = $this->markdown()->convert($node->content())->getContent();

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
PHP_NEW;

    if (str_contains($renderer, $oldMarkdown)) {
        $renderer = str_replace($oldMarkdown, $newMarkdown, $renderer);
    } else {
        $log('renderMarkdown-Block nicht exakt gefunden. Bitte ggf. manuell prüfen.');
    }

    $write($rendererFile, $renderer);

    $cssFile = $root . '/public/assets/css/treeforge.css';

    if (file_exists($cssFile)) {
        $css = file_get_contents($cssFile);

        if (!str_contains($css, '.tf-node-markdown h1')) {
            $css .= <<<'CSS'

.tf-node-markdown{
  text-align:left;
  line-height:1.6;
}

.tf-node-markdown h1,
.tf-node-markdown h2,
.tf-node-markdown h3{
  color:var(--tf-green);
  margin-top:0;
}

.tf-node-markdown strong{
  color:var(--tf-green);
}

.tf-node-markdown ul,
.tf-node-markdown ol{
  padding-left:1.35rem;
}

.tf-node-markdown p:last-child,
.tf-node-markdown ul:last-child,
.tf-node-markdown ol:last-child{
  margin-bottom:0;
}
CSS;

            $write($cssFile, $css);
        }
    }

    $write($root . '/docs/commonmark-rendering.md', <<<'MD'
# CommonMark Markdown Rendering

Patch 025 rendert MarkdownNodes mit `league/commonmark`.

## Voraussetzung

```bash
composer require league/commonmark
```

## Verhalten

MarkdownNode Content:

```markdown
# TreeForge

Das ist **Markdown**.

- Struktur
- Content
- Layers
```

wird im Frontend zu echtem HTML.

## Sicherheit

Der Converter wird mit sicheren Optionen initialisiert:

```php
[
  'html_input' => 'strip',
  'allow_unsafe_links' => false,
]
```

Im Explorer bleibt Markdown weiterhin als Markdown-Code sichtbar.

MD);

    $log('Patch 025 CommonMark Markdown Rendering fertig');
};
