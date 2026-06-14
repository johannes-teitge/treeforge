# Explorer V2 Save Node Properties

Patch 110 aktiviert den Speichern-Button im Property Editor.

## Ablauf

```text
Node auswählen
Property ändern
Speichern
POST /api/explorer-v2/mutate.php
action = update-node
JSON speichern
Reload
```

## Basisfelder

```html
data-node-base="title"
data-node-base="status"
data-node-base="visibility"
data-node-base="editor_note"
```

## Properties

```html
data-node-property="content.alt"
data-node-property="spacing.padding"
data-node-property="design.background"
data-node-property="custom_css"
```

## API Payload

```json
{
  "action": "update-node",
  "payload": {
    "node_id": "node_xyz",
    "base": {
      "title": "Hero"
    },
    "properties": {
      "content": {
        "alt": "Bildbeschreibung"
      }
    }
  }
}
```