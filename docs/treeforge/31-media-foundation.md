# Media Foundation

Patch 041 ergänzt die Grundlage für die Medienverwaltung.

## Struktur

```text
storage/media/
├── originals/
├── meta/
└── cache/
```

## Idee

TreeForge speichert Originale dauerhaft.

Abgeleitete Größen werden später nur bei Bedarf erzeugt und im Cache gespeichert.

```text
Original
↓
Meta
↓
Render Cache
```

Der Cache ist wegwerfbar und kann jederzeit neu aufgebaut werden.

## Dateien

```text
app/Modules/Media/MediaConfig.php
app/Modules/Media/MediaMeta.php
app/Modules/Media/MediaScanner.php
app/Modules/Media/MediaManager.php
public/admin/media/index.php
public/assets/css/media.css
```

## Route

```text
/admin/media/
```

## Noch nicht enthalten

- Upload
- Edit Modal
- Kategorieverwaltung
- Media Picker
- Render-API für Derivate

Diese Funktionen folgen in späteren Patches.