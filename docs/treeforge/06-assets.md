# Asset-System

TreeForge lädt Assets zentral und kontrolliert.

Assets werden nicht wild direkt in Nodes ausgegeben, sondern gesammelt und später zentral gerendert.

## Asset-Arten

- Core-CSS
- Editor-CSS
- Frontend-CSS
- Frontend-JS

## Core-Assets

Diese werden immer geladen:

```text
treeforge.css
treeforge-editor.css
```

## Node-Assets

Eine Node kann eigene Assets registrieren.

```php
public function getAssets(): array
{
    return [
        'editor_css' => 'editor.css',
        'frontend_css' => 'frontend.css',
        'frontend_js' => 'frontend.js'
    ];
}
```

Alternativ können Assets in der `node.json` definiert werden.

```json
{
  "assets": {
    "editor_css": "editor.css",
    "frontend_css": "frontend.css",
    "frontend_js": "frontend.js"
  }
}
```

## AssetCollector

Beim Rendern sammelt TreeForge alle verwendeten Assets.

Nur Assets von Nodes, die im aktuellen Baum vorkommen, werden geladen.

Beispiel:

```text
RootNode
├── Section
├── Columns
├── Image
└── Accordion
```

Geladen werden dann nur:

```text
section.css
columns.css
image.css
accordion.css
accordion.js
```

## CSS-Regeln

Alle TreeForge-Klassen nutzen Prefix:

```css
.tf-section {}
.tf-container {}
.tf-columns {}
.tf-column {}
.tf-node-demo {}
```

## Inline-Styles

Inline-Styles sollen vermieden werden.

Erlaubt sind sie nur für klar begrenzte Layoutwerte, wenn diese vom Editor kontrolliert werden.

Beispiele:

- Abstand
- Breite
- Hintergrundfarbe
- Textausrichtung

Freies CSS sollte über CSS-Klassen oder Node-Assets erfolgen.
