# Media Cache Busting

Patch 062 ergänzt Cache Busting für Media URLs.

## Problem

Nach dem Ersetzen eines Bildes war die neue Datei erst nach Strg+F5 sichtbar.

## Lösung

Media URLs bekommen automatisch einen Versionsparameter:

```text
/api/media/file.php?path=2026/06/bild.webp&v=1780000000
```

Der Wert basiert primär auf:

```text
filemtime(original)
```

Wenn die Datei ersetzt wird, ändert sich `filemtime`, damit ändert sich die URL und der Browser lädt neu.

## Betroffene Bereiche

- Media Grid
- Media Edit Preview
- Original-Link bleibt ohne Cache-Buster, sofern direkt geöffnet