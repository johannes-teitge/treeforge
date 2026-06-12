# Media Replacement Engine

Patch 061 stabilisiert das Ersetzen von Medien.

## Verhalten

Beim Ersetzen:

```text
Media-ID bleibt gleich
relative_path bleibt gleich
Ziel-Dateiname bleibt gleich
alte Datei wird gesichert
Originaldatei wird wirklich überschrieben
Meta-Daten werden aktualisiert
versions[] wird geschrieben
Cache wird vorbereitet invalidiert
```

## Speicherort alter Versionen

```text
storage/media/versions/...
```

## Formatwechsel

Noch nicht erlaubt.

```text
webp ersetzt webp
jpg ersetzt jpg
png ersetzt png
svg ersetzt svg
```

Das hält bestehende URLs stabil.