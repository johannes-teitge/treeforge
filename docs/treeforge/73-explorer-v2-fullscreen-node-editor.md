# Explorer V2 Fullscreen Node Editor

Patch 084 ergänzt einen großen Modal-Editor.

## Öffnen

Buttons mit:

```html
data-large-editor="#textareaId"
```

oder:

```html
id="tfv2OpenLargeEditor"
```

## Verhalten

- öffnet 90%-Modal
- kopiert Wert aus Originalfeld
- Übernehmen schreibt zurück
- Abbrechen verwirft Änderung
- Escape schließt
- Ctrl/Cmd + Enter übernimmt

## Geeignet für

- Text
- Markdown
- CSS
- HTML
- JSON