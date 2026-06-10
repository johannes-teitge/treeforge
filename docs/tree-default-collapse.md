# Tree Default Collapse

Patch 023 ändert den Startzustand des Explorers.

## Neuer Default

Beim ersten Laden:

```text
▾ Startseite
   Text
   Image
   Button
   ▸ Columns
```

Die Startseite bleibt offen.

Alle verschachtelten Nodes mit Kindern sind zunächst geschlossen.

## Speicherung

Der Zustand wird weiterhin in `localStorage` gespeichert.

## Reset

Zum Zurücksetzen im Browser:

```js
localStorage.removeItem('treeforge.explorer.collapsed');
localStorage.removeItem('treeforge.explorer.collapseInitialized');
```

Danach Seite neu laden.
