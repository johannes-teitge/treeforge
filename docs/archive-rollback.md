# Archive & Rollback

Patch 015 ergänzt Archivansicht und Rollback.

## Archivformat

Neue Struktur:

```text
storage/workspaces/archive/pages/home/
├─ 2026-06-10-001744.json
├─ 2026-06-10-002100.json
└─ 2026-06-10-003015.json
```

Alte Struktur wird weiterhin gelesen:

```text
storage/workspaces/archive/2026-06-10-001744/home.json
```

## Restore

Beim Wiederherstellen einer Archivversion passiert:

```text
1. aktuelle Published-Version wird archiviert
2. gewählte Archivversion wird nach Published kopiert
3. Workflow-Status wird auf restored_from_archive gesetzt
```

## API

```text
POST /api/archive/restore.php
```

Payload:

```json
{
  "page": "home",
  "version": "2026-06-10-001744"
}
```

## Demo-Workflow

```text
Draft ändern
↓
Review
↓
Publish
↓
Archive entsteht
↓
Archivversion ansehen
↓
Rollback
↓
Published ist wieder alte Version
```
