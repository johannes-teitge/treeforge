# Node Properties Groups + Update API Foundation

Patch 108 führt strukturierte Property-Gruppen ein.

## Neue Struktur

```json
{
  "properties": {
    "content": {},
    "layout": {},
    "spacing": {},
    "design": {},
    "behavior": {},
    "advanced": {},
    "custom_css": ""
  }
}
```

## Basisfelder bleiben oben

```text
id
type
title
status
visibility
editor_note
editor_notes
created_at
updated_at
children
properties
```

## Neue Mutation Action

```text
update-node
```

## Payload

```json
{
  "action": "update-node",
  "payload": {
    "node_id": "node_xyz",
    "base": {
      "title": "Neuer Titel",
      "status": "active",
      "visibility": "visible",
      "editor_note": ""
    },
    "properties": {
      "content": {
        "alt": "Bildbeschreibung"
      },
      "spacing": {
        "padding": "2rem"
      }
    }
  }
}
```

## Ziel

Grundlage für den Save-Button im Explorer V2.