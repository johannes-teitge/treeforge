# Archive JSON Export

Patch 034 ergänzt den JSON-Export für Archivversionen.

## Route

```text
/api/archive/export-json.php?version=<version>&page=home
```

## Verwendung

Im Archive Center gibt es pro Archivversion einen Button:

```text
JSON Export
```

Der Browser lädt eine Datei herunter:

```text
treeforge-archive-home-2026-06-11-123456.json
```

## Exportformat

```json
{
  "treeforge_export": {
    "type": "archive-json",
    "format_version": "1.0.0",
    "exported_at": "2026-06-11T10:00:00+02:00",
    "page": "home",
    "archive_version": "2026-06-11-123456",
    "source": "TreeForge Archive Center"
  },
  "page": {}
}
```

## Hinweis

Dieser Export enthält nur JSON-Daten.

Medien werden noch nicht mit exportiert. Dafür ist später ein ZIP-Export mit Manifest und Media-Mapping vorgesehen.