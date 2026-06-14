# Explorer V2 Delete Node API Integration

Patch 099 verbindet das Node-Menü "Löschen" mit der Mutation API.

## Ablauf

```text
⋯ → Löschen
Sicherheitsabfrage
POST /api/explorer-v2/mutate.php
action = delete
JSON speichern
Reload
```

## Payload

```json
{
  "page": "home",
  "workspace": "draft",
  "action": "delete",
  "payload": {
    "node_id": "node_xyz"
  }
}
```

## Schutz

Die RootNode kann serverseitig nicht gelöscht werden.