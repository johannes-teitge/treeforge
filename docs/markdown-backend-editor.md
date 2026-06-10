# Markdown Backend Editor

Patch 026 ergänzt Markdown im Backend.

## Funktionen

- MarkdownNode bekommt einen eigenen Editor im Inspector.
- Markdown Preview wird als echtes HTML gerendert.
- Speichern läuft async ohne Reload.
- Speichern erfolgt nur im Draft Workspace.

## API

```text
POST /api/node/save-markdown.php
```

## Sicherheit

Markdown wird mit `league/commonmark` und sicheren Optionen gerendert.
