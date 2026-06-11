# TreeForge Core Foundation

Patch 031 legt die ersten Core-Basisklassen an.

## Dateien

```text
app/TreeForge/
├── AbstractTreeForgeNode.php
├── NodeRegistry.php
├── NodeManifest.php
├── NodeValidator.php
├── AssetCollector.php
├── EditorSchemaBuilder.php
├── NodeCapabilities.php
└── Nodes/
    └── DemoNode.php
```

## Ziel

Dieser Patch verändert den bestehenden Editor noch nicht.

Er schafft nur die Grundlage für:

- zentrale Node-Definitionen
- automatische Node-Registrierung
- Drop-in-Nodes
- Editor-Schema
- Asset-Sammlung
- Parent/Child-Regeln
- DemoNode als Entwicklerbeispiel

## Nächster Schritt

Im nächsten Patch können bestehende Nodes wie Columns, Column, Text, Image und Markdown schrittweise auf die neue Struktur vorbereitet werden.