# CSS Nodes as Style

Patch 024 ändert das Rendering von CSS Nodes.

## Vorher

CSS Nodes wurden sichtbar als Codeblock ausgegeben.

```html
<pre>.demo { color: red; }</pre>
```

## Jetzt

CSS Nodes werden gesammelt und im `<head>` als `<style>` eingebunden.

```html
<style>
/* CSS Node: node_css_demo */
.demo { color: red; }
</style>
```

## Wichtig

Der Explorer zeigt CSS Nodes weiterhin im Tree und Inspector.

Auf der gerenderten Seite sind CSS Nodes nicht mehr als sichtbarer Content-Block vorhanden.

## Später

Mögliche Erweiterungen:

- CSS Scope pro Teilbaum
- CSS Validierung
- CSS Editor mit CodeMirror
- CSS Bundling
- CSS-Minifizierung
