# Fix Media Edit Preview URL

Patch 059 repariert die Bildvorschau in der Media-Edit-Seite.

## Problem

Im Grid wurden Bilder angezeigt, in der Edit-Seite aber nicht.

## Fix

Die Edit-Seite verwendet nun denselben stabilen Dateizugriff über:

```text
/api/media/file.php?path=...
```

Damit funktionieren auch Medien in Unterordnern wie:

```text
2026/06/datei.webp
```