# Speicherung

TreeForge speichert Nodes generisch.

Eine Node speichert nicht selbst.

Der Core verwaltet:

- insert
- update
- delete
- read
- sort
- move
- active
- valid_from
- valid_until
- created_at
- updated_at

## Beispiel

```json
{
  "id": 15,
  "content_id": 3,
  "parent_id": 7,
  "position": 2,
  "type": "text",
  "active": true,
  "valid_from": null,
  "valid_until": null,
  "data": {
    "content": "Hallo Welt"
  }
}
```

## Aufgaben der Node

Die Node definiert:

- Default-Daten
- Editor-Schema
- Rendering
- Assets
- erlaubte Parents
- erlaubte Children

## Aufgaben des Core

Der Core übernimmt:

- Speichern
- Lesen
- Sortieren
- Verschieben
- Löschen
- Duplizieren
- Validieren
- Sichtbarkeit prüfen
- zeitliche Gültigkeit prüfen

## JsonSQL-Struktur

Mögliche Tabellen beziehungsweise JSON-Dateien:

```text
treeforge_nodes.json
treeforge_node_revisions.json
treeforge_node_meta.json
```

Für den Anfang reicht eine generische Node-Struktur.

Revisionen und Meta-Daten können später ergänzt werden.
