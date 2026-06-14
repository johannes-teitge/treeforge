# Explorer V2 Delay Reload After Mutation

Patch 096 verzögert den Reload nach erfolgreichem Hinzufügen.

## Problem

Die Toast-Dauer war bereits auf 4500 ms gesetzt, aber die Seite wurde nach 350 ms neu geladen.

## Änderung

```text
350 ms → 2500 ms
```

## Betroffene Dateien

```text
public/assets/js/explorer-v2-add-node-dialog.js
public/assets/js/explorer-v2-force-add-node-submit.js
```