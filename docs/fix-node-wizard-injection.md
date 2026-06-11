# Fix Node Wizard Injection

Patch 028 fügt den Node Wizard robuster ein.

## Prüfen

Im Seitenquelltext muss vorhanden sein:

```html
id="tfAddNode"
id="tfNodeWizard"
explorer.js?v=028
```

In der JS-Datei muss vorhanden sein:

```js
function initNodeWizard
```

## Test

```text
/explorer?workspace=draft
```

Dann auf `+ Node` klicken.
