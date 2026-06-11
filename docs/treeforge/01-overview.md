# TreeForge Architektur

TreeForge ist ein erweiterbares Node-basiertes Content-System.

Jeder Content-Datensatz besitzt eine RootNode. Diese RootNode kann eine Seite, ein Blogartikel, eine News, ein Produkt oder ein anderer Content-Typ sein.

Unterhalb der RootNode werden Content-Nodes angeordnet.

## Beispiel

```text
RootNode
└── Section
    └── Columns
        ├── Column
        │   └── Text
        └── Column
            └── Image
```

## Hauptbestandteile

- RootNode
- NodeRegistry
- AbstractTreeForgeNode
- EditorSchema
- Renderer
- AssetCollector
- Storage
- CustomNodes

## Ziel

Neue Nodes sollen ohne Änderung am TreeForge-Core eingebunden werden können.

Eine Node beschreibt selbst:

- was sie ist
- wie sie im Editor angezeigt wird
- welche Properties sie besitzt
- welche Children erlaubt sind
- welche Assets sie benötigt
- wie sie im Frontend gerendert wird

Der Core übernimmt:

- Registrierung
- Speichern
- Lesen
- Sortieren
- Verschieben
- Validierung
- Rendering-Pipeline
- Asset-Sammlung
