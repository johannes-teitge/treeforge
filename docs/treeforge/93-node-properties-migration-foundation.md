# Node Properties Migration Foundation

Patch 104a migriert vorhandene Nodes auf die neue `properties{}`-Struktur.

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

## Nodespezifische Felder wandern nach properties

Vorher:

```json
{
  "type": "ImageNode",
  "alt": "Bild",
  "caption": "Text"
}
```

Nachher:

```json
{
  "type": "ImageNode",
  "status": "active",
  "visibility": "visible",
  "editor_note": "",
  "properties": {
    "alt": "Bild",
    "caption": "Text"
  }
}
```

## Wichtig

`children` bleibt die Baumstruktur.

`parent_id` wird nicht gespeichert, sondern bei Bedarf berechnet.