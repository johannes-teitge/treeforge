# Fix Media Edit Preview Cache Parameter

Patch 060 repariert den Cache-Buster der Media-Edit-Vorschau.

## Problem

Die URL war sinngemäß:

```text
/api/media/file.php?path=2026/06/bild.webp?v=...
```

Der Cache-Parameter landete dadurch im `path`.

## Fix

Jetzt:

```text
/api/media/file.php?path=2026/06/bild.webp&v=...
```