# Explorer V2 Node Toolbar Foundation

Patch 081 ergänzt eine Toolbar pro Node.

## Aktionen

```text
+ Kind
Bearbeiten
Kopieren
Als Referenz einfügen
Duplizieren
Verschieben
Löschen
```

## Wichtig

Dieser Patch ist nur UI/Foundation.

Noch keine echten Datenänderungen.

## Referenz-Konzept

Später:

```json
{
  "id": "node_ref_123",
  "type": "ReferenceNode",
  "source_node_id": "node_master_hero",
  "mode": "live"
}
```

## Clipboard

Temporär im Browser:

```js
window.TreeForgeV2Clipboard
```