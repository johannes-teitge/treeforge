# Installer Concept

TreeForge benötigt einen Installer für neue Installationen.

## Ablauf

```text
Systemcheck
↓
Lizenz / Willkommen
↓
Sprache wählen
↓
Storage wählen
↓
Site-Daten
↓
Admin-User anlegen
↓
Config schreiben
↓
Storage initialisieren
↓
Installation sperren
```

## Systemcheck

- PHP-Version
- benötigte Extensions
- Schreibrechte
- Composer Autoload
- Storage-Verzeichnis
- public/media oder storage/media
- optional SQLite/MySQL Verbindung

## Installation abgeschlossen

Nach Abschluss wird eine Installationssperre geschrieben.

```text
storage/system/installed.lock
```
