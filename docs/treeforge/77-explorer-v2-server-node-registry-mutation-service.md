# Explorer V2 Server Node Registry + Mutation Service

Patch 088 ergänzt die Backend-Foundation für echte Node-Änderungen.

## Neue Klassen

```text
app/Modules/ExplorerV2/NodeTypeRegistry.php
app/Modules/ExplorerV2/NodeMutationService.php
```

## Neuer API-Endpunkt

```text
/api/explorer-v2/mutate.php
```

## Unterstützte Actions

```text
add
delete
duplicate
paste-copy
paste-reference
```

## Beispiel

```json
{
  "page": "home",
  "workspace": "draft",
  "action": "add",
  "payload": {
    "parent_id": "home",
    "type": "TextNode"
  }
}
```

## Parent/Child-Regeln

```text
RootNode      -> *
ColumnsNode   -> ColumnNode
ColumnNode    -> *
TextNode      -> keine Kinder
ImageNode     -> keine Kinder
ButtonNode    -> keine Kinder
```

## Nächster Schritt

Patch 089 verbindet die Explorer-V2-UI mit dieser API.