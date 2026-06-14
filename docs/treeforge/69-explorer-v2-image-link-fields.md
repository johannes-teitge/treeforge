# Explorer V2 Image Link Fields

Patch 079 ergänzt den ImageNode-Editor.

## Neue Felder

```text
Ziel-URL
Target
```

## Zweck

Bilder können später optional klickbar gemacht werden.

Beispiele:

```text
Logo → /
Teaserbild → /blog/artikel
Produktbild → /produkte/produktname
```

## Vorgesehene JSON-Felder

```json
{
  "link_url": "/kontakt",
  "link_target": "_self"
}
```