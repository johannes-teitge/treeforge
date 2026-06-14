# Explorer V2 Type Editors DOM Fix

Patch 078 erzeugt die typspezifischen Editorfelder per DOM-JavaScript.

## Warum?

Patch 077 hatte die PHP-Stelle im Renderer nicht zuverlässig getroffen.

## Vorteile

- robust gegen Renderer-Struktur
- erkennt Nodes aus `data-node-json`
- Fallback über sichtbaren Node-Text
- ersetzt den rechten Editorbereich dynamisch

## Unterstützt

- Text
- Markdown
- Image
- Button
- Columns
- CSS
- Fallback