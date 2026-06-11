# Fix Node Wizard Init Timing

Patch 029 behebt, dass `+ Node` keine Reaktion zeigt.

## Ursache

Der Wizard-HTML-Block wurde nach dem Script eingefügt:

```html
<script src="/assets/js/explorer.js?v=028"></script>

<div id="tfNodeWizard">...</div>
```

Dadurch konnte `initNodeWizard()` das Modal noch nicht finden.

## Lösung

`initNodeWizard()` läuft jetzt erst nach `DOMContentLoaded`.

## Test

```text
/explorer?workspace=draft
```

Danach hart neu laden:

```text
Strg + F5
```

Dann auf `+ Node` klicken.
