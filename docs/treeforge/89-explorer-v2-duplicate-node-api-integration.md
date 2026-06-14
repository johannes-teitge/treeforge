# Explorer V2 Duplicate Node API Integration

Patch 100 verbindet das Node-Menü "Duplizieren" mit der Mutation API.

## Ablauf

```text
⋯ → Duplizieren
POST /api/explorer-v2/mutate.php
action = duplicate
JSON speichern
Reload
```

## Payload

```json
{
  "page": "home",
  "workspace": "draft",
  "action": "duplicate",
  "payload": {
    "node_id": "node_xyz"
  }
}
```

## Verhalten

Die Kopie wird direkt nach der Original-Node eingefügt.

Verschachtelte Kinder werden rekursiv mitkopiert.