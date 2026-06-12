# Fix Media Repository Recursive Metadata

Patch 055 repariert den MediaRepository-Zugriff.

## Fehler

Neue Uploads wurden nicht auf der Edit-Seite gefunden, obwohl sie im Grid sichtbar waren.

Ursache:

```text
storage/media/meta/YYYY/MM/*.json
```

wurde nicht zuverlässig rekursiv durchsucht.

Außerdem wurden ältere Metadaten ohne `kind` als `FILE` behandelt.

## Fix

- RecursiveDirectoryIterator für alle JSON-Metadaten
- robuste `kind`-Erkennung aus Extension und MIME-Type
- alte Metadaten bleiben kompatibel
- `findById()` funktioniert jetzt auch für neue Uploads