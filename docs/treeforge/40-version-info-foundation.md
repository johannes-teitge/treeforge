# Version Info Foundation

Patch 050 ergänzt stabile Versionsinformationen.

## Dateien

```text
VERSION
BUILD
app/Core/System/Version.php
```

## Warum?

Git-Informationen sind im Backend nicht immer zuverlässig verfügbar.

Gründe:

- `.git` wird auf Live-Systemen oft nicht deployed
- `shell_exec()` kann deaktiviert sein
- Git ist unter Windows/Laragon nicht immer im PHP-Pfad
- `composer.json` enthält nicht zwingend eine Version

## Lösung

TreeForge liest primär:

```text
VERSION
BUILD
```

Git Tag und Commit sind nur zusätzliche Diagnosewerte.

## Später

Die Version-Klasse ist Grundlage für:

```text
Update API
Support-Diagnose
Systemstatus
Kompatibilitätsprüfung
Release Channel
```