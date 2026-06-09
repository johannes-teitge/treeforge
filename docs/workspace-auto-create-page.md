# Workspace Auto Create Page

Fehlt eine Seite in einem Workspace, erzeugt TreeForge sie automatisch.

## Reihenfolge

Beispiel: `review/home.json` fehlt.

```text
1. aus draft kopieren
2. sonst aus published kopieren
3. sonst leere Seite erzeugen
```

## Leere Seite

```json
{
  "id": "home",
  "type": "page",
  "title": "Home",
  "children": []
}
```

## Vorteil

Der Explorer läuft nicht mehr in einen Fatal Error, wenn ein Workspace noch keine Seite besitzt.
