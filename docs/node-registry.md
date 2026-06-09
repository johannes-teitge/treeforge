# TreeForge Node Registry

Die Node Registry ist das zentrale Verzeichnis aller bekannten Node-Typen.

## Ziel

Statt die NodeFactory fest mit einzelnen Klassen zu verdrahten, werden Node-Typen registriert.

```php
NodeRegistry::register('text', TextNode::class);
NodeRegistry::register('image', ImageNode::class);
NodeRegistry::register('button', ButtonNode::class);
NodeRegistry::register('columns', ColumnsNode::class);
```

## Ablauf

```text
JSON
  ↓
NodeRegistry
  ↓
NodeFactory
  ↓
Node-Objekt
  ↓
Renderer
```

## Vorteil

Die Registry ist später Grundlage für:

- Editor-Blockauswahl
- Property Panels
- Modul-System
- Import/Export
- Headless API
- Custom Nodes

## Aktuelle Node-Typen

```text
text
image
button
columns
```
