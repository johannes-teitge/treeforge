# Explorer V2 Add Node API Integration

Patch 092 verbindet den Add-Node-Dialog mit der Mutation API.

## Ablauf

```text
⋯ → Hinzufügen
Node-Typ wählen
Hinzufügen vorbereiten
POST /api/explorer-v2/mutate.php
JSON wird gespeichert
Explorer reload
```

## API Payload

```json
{
  "page": "home",
  "workspace": "draft",
  "action": "add",
  "payload": {
    "parent_id": "home",
    "type": "TextNode",
    "defaults": {}
  }
}
```

## Hinweis

Der erste echte Schreibzugriff erfolgt im aktuellen Workspace.

Wenn kein Workspace in der URL steht, wird `draft` verwendet.