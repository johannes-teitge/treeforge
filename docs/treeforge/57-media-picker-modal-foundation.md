# Media Picker Modal Foundation

Patch 067 ergänzt einen wiederverwendbaren Media Picker.

## JavaScript API

```js
window.TreeForgeMediaPicker.open(function(media) {
  console.log(media.id);
});
```

## Automatische Nutzung über Attribute

```html
<input id="hero_image" name="hero_image">
<button data-media-picker-target="#hero_image">
  Aus Medienbibliothek auswählen
</button>
```

Optional mit Vorschau:

```html
<div id="hero_preview" class="tf-media-picker-preview"></div>
<button
  data-media-picker-target="#hero_image"
  data-media-picker-preview="#hero_preview">
  Auswählen
</button>
```

## Picker Endpoint

```text
/admin/media/picker.php
```

## Payload

Der Picker liefert:

```json
{
  "id": "...",
  "title": "...",
  "alt": "...",
  "filename": "...",
  "relative_path": "...",
  "url": "...",
  "preview_url": "...",
  "kind": "image",
  "mime": "image/webp",
  "width": 1200,
  "height": 800,
  "size": 123456,
  "category": "hero"
}
```

## Nächste Schritte

- Integration in ImageNode
- Integration in HeroNode
- Integration in Page Settings Social Image
- Suche
- Mehrfachauswahl für GalleryNode