# Patch 123 – Workspace Pages Overview

Patch 123 räumt die Seitenverwaltung auf.

## Neue Wahrheit

Die Seitenübersicht liest nicht mehr primär aus:

```text
storage/pages/pages.json
```

sondern aus:

```text
storage/workspaces/draft/pages/*.json
storage/workspaces/review/pages/*.json
storage/workspaces/published/pages/*.json
```

`storage/pages/pages.json` wird nur noch als Übergangs-Fallback gelesen, falls dort ältere Metadaten wie `parent_id`, `position`, `language` oder `template` stehen.

## Neue Seitenübersicht

```text
/admin/pages/
```

Funktionen:

- Seiten aus Workspace-Dateien auflisten
- Draft/Review/Published-Badges
- Seiten im Draft anlegen
- Seitentitel, Parent, Position, Sprache und Template ändern
- Draft nach Review kopieren
- Draft veröffentlichen
- Seite als Draft duplizieren
- Draft löschen = in `storage/trash/pages` verschieben
- Direkter Link zum Explorer V2

## Legacy aufräumen

Erst prüfen:

```bash
php tools/archive-legacy-pages.php --dry-run
```

Dann archivieren:

```bash
php tools/archive-legacy-pages.php
```

Das verschiebt `storage/pages` nach:

```text
storage/legacy/pages-YYYYmmdd-His
```

Es wird nichts hart gelöscht.