# Basis-Properties

Jede Node besitzt System-Properties und eigene Node-Daten.

## System-Properties

Diese Felder werden vom TreeForge-Core verwaltet:

- id
- content_id
- parent_id
- position
- type
- active
- valid_from
- valid_until
- css_id
- css_class
- created_at
- updated_at
- created_by
- updated_by

## Node-Daten

Node-spezifische Daten liegen gesammelt im Feld `data`.

Beispiele:

### TextNode

```json
{
  "content": "Hallo Welt",
  "text_align": "left"
}
```

### ImageNode

```json
{
  "src": "/media/test.jpg",
  "alt": "Beispielbild",
  "caption": "Bildunterschrift"
}
```

## Komplettes Beispiel

```json
{
  "id": 17,
  "content_id": 3,
  "parent_id": 5,
  "type": "image",
  "position": 2,
  "active": true,
  "valid_from": null,
  "valid_until": null,
  "css_id": "",
  "css_class": "",
  "data": {
    "src": "/media/test.jpg",
    "alt": "Beispielbild"
  }
}
```

## Sichtbarkeit

Eine Node wird nur gerendert, wenn sie aktiv und zeitlich gültig ist.

```php
public function isRenderable(array $node): bool
{
    if (empty($node['active'])) {
        return false;
    }

    $now = time();

    if (!empty($node['valid_from']) && strtotime($node['valid_from']) > $now) {
        return false;
    }

    if (!empty($node['valid_until']) && strtotime($node['valid_until']) < $now) {
        return false;
    }

    return true;
}
```
