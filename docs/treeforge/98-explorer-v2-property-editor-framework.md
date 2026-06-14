# Explorer V2 Property Editor Framework

Patch 109 ergänzt den neuen Property Editor im Explorer V2.

## Gruppen

```text
Content
Layout
Spacing
Design
Behavior
Advanced
Custom CSS
```

## Datenattribute

Property-Felder:

```html
data-node-property="content.alt"
data-node-property="spacing.padding"
data-node-property="design.background"
```

Basisfelder:

```html
data-node-base="title"
data-node-base="status"
data-node-base="visibility"
data-node-base="editor_note"
```

## Noch offen

Patch 110 verbindet diese Felder mit:

```text
update-node
```

und speichert die Werte in JSON.