# Fix Media Original Delivery

Patch 042 behebt die Anzeige von Originalbildern in der Medienbibliothek.

## Problem

Die Medien liegen unter:

```text
storage/media/originals/
```

Die bisherige URL zeigte aber auf:

```text
/media/originals/...
```

Dieser Pfad ist nicht automatisch öffentlich erreichbar.

## Lösung

Ein sicherer File-Endpunkt liefert Originale aus:

```text
/api/media/file.php?path=landschaft.jpg
```

`MediaConfig::publicOriginalUrl()` erzeugt nun diese URL.

## Sicherheit

Der Endpunkt prüft:

- kein leerer Pfad
- kein `..`
- Datei muss innerhalb von `storage/media/originals` liegen
- nur erlaubte Bildtypen
- Content-Type wird passend gesetzt

## Später

Für Produktion kann man später alternativ einen Symlink oder Rewrite für Medien verwenden.