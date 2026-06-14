# Explorer V2 Clipboard Engine Foundation

Patch 085 ergänzt ein erstes Node-Clipboard im Browser.

## Funktionen

- Kopieren legt Node in localStorage
- Referenz legt Node im Referenzmodus in localStorage
- Menü zeigt bei vorhandenem Clipboard:
  - Einfügen
  - Referenz einfügen
  - Clipboard leeren
- Sidebar zeigt Clipboard-Info

## Storage Key

```text
tfv2.nodeClipboard
```

## API

```js
window.TreeForgeV2ClipboardApi.read()
window.TreeForgeV2ClipboardApi.clear()
```

## Noch offen

- echte JSON-Mutation
- Einfügen speichern
- ReferenzNode erzeugen
- Servervalidierung