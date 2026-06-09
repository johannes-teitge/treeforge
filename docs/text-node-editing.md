# TextNode Editing

Patch 013 ergänzt die erste echte Bearbeitungsfunktion.

## Regel

Bearbeitet wird vorerst ausschließlich im Draft Workspace.

```text
published = live, readonly
draft     = editierbar
review    = später Freigabe
```

## API

```text
POST /api/node/save-text.php
```

Payload:

```json
{
  "page": "home",
  "node": "node_hero",
  "content": "Neuer Text"
}
```

## Ablauf

```text
Explorer
↓
TextNode anklicken
↓
Content bearbeiten
↓
In Draft speichern
↓
Draft Preview prüfen
```

## Warum wichtig?

TreeForge kann nun erstmals nicht nur anzeigen, sondern Inhalte ändern.
