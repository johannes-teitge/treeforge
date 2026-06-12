# Media Replace / Versioning

Patch 057 ergänzt Datei-Ersetzen mit Versionierung.

## Verhalten

```text
Media-ID bleibt gleich
relative_path bleibt gleich
öffentlicher Ziel-Dateiname bleibt gleich
Metadaten bleiben erhalten
alte Datei wird als Version gesichert
```

## Speicherort alter Versionen

```text
storage/media/versions/...
```

## Formatwechsel

Aktuell bewusst nicht erlaubt.

```text
jpg ersetzt jpg
png ersetzt png
svg ersetzt svg
zip ersetzt zip
```

Warum?

Bestehende URLs und Renderer bleiben stabil.

## Später möglich

- Formatwechsel optional erlauben
- Cache invalidieren
- Version zurückholen
- Versionsvergleich