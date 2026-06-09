# TreeForge Inspector

Der Inspector zeigt Informationen über eine ausgewählte Node.

## Funktionen

- Node ID
- Node Type
- Workspace
- Anzahl Kinder
- Properties
- Raw JSON

## NodeInspector

Die zentrale Klasse ist:

```text
app/Core/NodeInspector.php
```

Sie analysiert Node-Arrays und liefert strukturierte Metadaten.

## Warum wichtig?

Der Inspector ist die Grundlage für:

- readonly Explorer
- späterer Property Editor
- Headless API
- Import/Export-Validierung
- Node Debugging

## Aktueller Stand

Der Inspector ist readonly.

Bearbeitung folgt später mit:

```text
Patch 011 - TextNode Editing
```
