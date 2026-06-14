# Fix Missing update-node Mutation

Patch 111 behebt:

```text
Unbekannte Mutation: update-node
```

## Ergänzt

```text
update-node
```

in `NodeMutationService::mutate()`.

## Ergänzt Methoden

```php
updateNode()
updateNodeInTree()
mergeProperties()
```

## Payload

```json
{
  "action": "update-node",
  "payload": {
    "node_id": "node_xyz",
    "base": {
      "title": "Neuer Titel"
    },
    "properties": {
      "content": {
        "text": "Neuer Text"
      }
    }
  }
}
```