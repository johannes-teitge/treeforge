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
