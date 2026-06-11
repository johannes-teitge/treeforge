# Archive Frontend Preview

Patch 032 behebt die Frontend-Vorschau für Archivversionen.

## Problem

Im Archivmodus zeigte der Button "Archiv ansehen" auf:

```text
/explorer?archive=<version>&page=home
```

Dadurch wurde wieder der Backend-Explorer geöffnet.

## Lösung

Der Button zeigt jetzt auf:

```text
/?archive=<version>&page=home
```

`public/index.php` kann Archivversionen laden und über den bestehenden `HtmlRenderer` ausgeben.

## Technischer Hinweis

Da `HtmlRenderer` aktuell ein `Page`-Objekt erwartet, wird die Archivversion für die Vorschau temporär unter folgendem Pfad gespeichert:

```text
storage/cache/archive-preview/
```

Diese Dateien dienen nur der Vorschau.