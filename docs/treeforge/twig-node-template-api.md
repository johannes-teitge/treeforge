# TreeForge Twig Node Template API

Diese Datei beschreibt die geplanten Variablen für Node-Templates.

## Allgemeine Node-Variablen

Jede Node bekommt später mindestens:

```text
node.id
node.type
node.dom_id
node.class
node.css_class
node.title
node.original_type
```

`node.dom_id` ist die HTML-ID. Sie kann aus `properties.advanced.css_id` kommen oder automatisch aus der Node-ID erzeugt werden.

`node.class` enthält die vollständige CSS-Klassenliste, z. B.:

```text
tf-node tf-node-text
```

## heading

```text
node.level
node.text
```

Beispiel:

```twig
<{{ node.level }} id="{{ node.dom_id }}" class="{{ node.class }}">
  {{ node.text }}
</{{ node.level }}>
```

## text

```text
node.content_html
```

`content_html` wird vorher serverseitig aus Text erzeugt:

```text
Leerzeile -> <p>
einzelner Zeilenumbruch -> <br>
```

Der Text wird vorher escaped. Deshalb ist diese Ausgabe erlaubt:

```twig
{{ node.content_html|raw }}
```

## image

```text
node.src
node.alt
node.caption
```

## button

```text
node.label
node.url
node.target
node.variant
```

## markdown

```text
node.html
```

`node.html` wird serverseitig aus Markdown erzeugt und bereinigt.

## html

```text
node.html
```

Achtung: HTML-Nodes sind echte Raw-HTML-Ausgabe und müssen später über Berechtigungen abgesichert werden.

## codeblock

```text
node.language
node.code
node.caption
node.show_line_numbers
node.wrap
```

`node.code` wird durch Twig automatisch escaped, wenn es ohne `|raw` ausgegeben wird.

## columns / column

```text
node.children_html
node.columns
node.gap
```

`children_html` ist bereits gerendertes HTML der Kind-Nodes.