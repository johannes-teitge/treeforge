# Custom Nodes

Eigene Nodes können als Drop-in-Ordner eingebunden werden.

## Struktur

```text
custom/nodes/
└── DemoBox/
    ├── node.json
    ├── DemoBoxNode.php
    ├── editor.css
    ├── frontend.css
    └── frontend.js
```

## node.json

```json
{
  "type": "demo_box",
  "class": "DemoBoxNode",
  "file": "DemoBoxNode.php",
  "label": "Demo Box",
  "icon": "fa-solid fa-box",
  "category": "Demo",
  "version": "1.0.0",
  "active": true,
  "assets": {
    "editor_css": "editor.css",
    "frontend_css": "frontend.css",
    "frontend_js": "frontend.js"
  }
}
```

## Automatische Registrierung

TreeForge scannt automatisch:

```text
custom/nodes/*/node.json
```

Aktive Nodes werden registriert.

```php
foreach ($nodeFolders as $folder) {
    $meta = json_decode(file_get_contents($folder . '/node.json'), true);

    if (empty($meta['active'])) {
        continue;
    }

    require_once $folder . '/' . $meta['file'];

    TreeForge::registerNode($meta['type'], new $meta['class']($meta));
}
```

## Wichtig

Custom Nodes speichern nicht selbst.

Der TreeForge-Core übernimmt:

- insert
- update
- delete
- read
- sort
- move

Eine Custom Node beschreibt nur:

- Metadaten
- Default-Daten
- Editor-Schema
- Rendering
- Assets
- Hierarchie-Regeln
