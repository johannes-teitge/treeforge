# Docs Viewer

Patch 036 ergänzt eine kleine Frontend-Ansicht für die TreeForge-Dokumentation.

## Route

```text
/docs-viewer/
```

## Quelle

Alle Dateien aus:

```text
docs/treeforge/*.md
```

werden automatisch in der Sidebar gelistet.

## Rendering

Wenn `league/commonmark` vorhanden ist, wird CommonMark verwendet.

Falls nicht, gibt es einen einfachen Fallback-Renderer.

## Zweck

Die Architektur-Dokumentation kann direkt im Browser gelesen werden, ohne die Markdown-Dateien einzeln öffnen zu müssen.