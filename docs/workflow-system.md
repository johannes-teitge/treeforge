# TreeForge Workflow System

TreeForge arbeitet mit Workspace-Layern.

## Grundidee

Die öffentliche Website soll stabil bleiben, während Änderungen vorbereitet und freigegeben werden.

```text
published = öffentliche Live-Version
draft     = Arbeitsversion
review    = Freigabeversion
archive   = alte Versionen
```

## Ordnerstruktur

```text
storage/workspaces/
├─ published/
│  └─ pages/
│     └─ home.json
├─ draft/
│  └─ pages/
│     └─ home.json
├─ review/
│  └─ pages/
└─ archive/
```

## Öffentliche Website

Die normale Website liest immer:

```text
storage/workspaces/published/pages/
```

Damit bleiben Änderungen unsichtbar, bis sie freigegeben werden.

## Vorschau

Für lokale Tests kann ein Workspace per URL gewählt werden:

```text
/?workspace=draft
/?workspace=review
```

Später wird daraus ein geschützter Preview-Link mit Token:

```text
/preview/home?token=abc123
```

## Publishing

Beim Publish passiert:

```text
draft → published
```

Die alte Published-Version wird vorher archiviert:

```text
published → archive/YYYY-MM-DD-HHMMSS/
```

## Warum Workspace statt Node-Level-Layer?

Für den Anfang wird die komplette Seite versioniert.

Das ist einfacher, robuster und leichter zu verstehen.

Später kann bei Bedarf Node-Level-Versionierung ergänzt werden.

## Workflow

```text
1. Öffentliche Seite läuft aus published.
2. Redakteur arbeitet in draft.
3. Marketing erhält Preview-Link.
4. Marketing gibt frei.
5. Draft wird published.
6. Alte Published-Version wandert ins Archiv.
```

## Vorteil

- Live-Seite bleibt stabil.
- Änderungen können geprüft werden.
- Freigaben werden möglich.
- Rollback wird möglich.
- Passt perfekt zu Export/Import und Site Packages.
