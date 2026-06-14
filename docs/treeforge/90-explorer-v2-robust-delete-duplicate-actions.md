# Explorer V2 Robust Delete + Duplicate Actions

Patch 101 behebt nicht funktionierendes Löschen und Duplizieren.

## Ursache

Die separaten JS-Dateien für Delete/Duplicate wurden nicht zuverlässig im Renderer geladen.
Dadurch lief nur die alte Toast-Logik:

```text
Löschen vorbereitet
Duplizieren vorbereitet
```

## Fix

Die echten Aktionen werden direkt an `explorer-v2.js` angehängt.

## Aktionen

```text
delete
duplicate
```

laufen jetzt über:

```text
/api/explorer-v2/mutate.php
```

## Danach

- Reload auf `workspace=draft`
- Cache-Buster
- bei Duplikat wird neue Node markiert