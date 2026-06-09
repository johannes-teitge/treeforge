# Code & Markdown Inspector

Patch 011 erweitert den Explorer um Code- und Markdown-Vorschauen.

## Neue Node-Typen

```text
css
markdown
```

## Beispiel CSS Node

```json
{
  "id": "node_css_demo",
  "type": "css",
  "content": ".demo { color: red; }"
}
```

## Beispiel Markdown Node

```json
{
  "id": "node_markdown_demo",
  "type": "markdown",
  "content": "# Überschrift\n\n**Fett**"
}
```

## Technik

Für das Syntax Highlighting wird zunächst Prism.js per CDN eingebunden.

Später kann Prism lokal ausgeliefert werden.

## Architektur

Die Klasse

```text
app/Core/InspectorPreviewRenderer.php
```

entscheidet, wie bestimmte Node-Typen im Inspector angezeigt werden.
