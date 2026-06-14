# Patch 144 – ChatGPT Export ZIP Tool

Dieser Patch ergänzt ein Export-Tool für saubere Projekt-ZIPs, die direkt in ChatGPT hochgeladen werden können.

## Ausführen

```bash
php tools/export-treeforge-for-chatgpt.php
```

Ausgabe:

```text
exports/chatgpt/treeforge-chatgpt-YYYYmmdd-His.zip
```

## Dry-Run

```bash
php tools/export-treeforge-for-chatgpt.php --dry-run
```

## Minimaler Export

```bash
php tools/export-treeforge-for-chatgpt.php --minimal
```

## Standard-Inhalte

Enthalten sind u. a.:

- `app/`
- `core/`
- `templates/`
- `patches/`
- `public/`
- `docs/`
- `tools/`
- `storage/workspaces/`
- `storage/pages/`
- `storage/system/`
- `composer.json`
- `composer.lock`

## Ausgeschlossen

Standardmäßig ausgeschlossen:

- `.git/`
- `vendor/`
- `node_modules/`
- `exports/`
- `storage/logs/`
- `storage/legacy/`
- `storage/cache/`
- `.env`
- Archive wie `.zip`, `.7z`, `.rar`
- Datenbanken wie `.sql`, `.sqlite`, `.db`
- private Schlüssel wie `.pem`, `.key`, `id_rsa`
- große Dateien über 5 MB

## Optionen

```bash
php tools/export-treeforge-for-chatgpt.php --max-file-mb=10
php tools/export-treeforge-for-chatgpt.php --no-storage
php tools/export-treeforge-for-chatgpt.php --include-legacy
php tools/export-treeforge-for-chatgpt.php --include-logs
php tools/export-treeforge-for-chatgpt.php --include-vendor
php tools/export-treeforge-for-chatgpt.php --output=exports/chatgpt/mein-export.zip
```

## Manifest

Das ZIP enthält zusätzlich:

```text
treeforge-export-manifest.json
```

Darin stehen:

- Erstellungszeitpunkt
- Optionen
- enthaltene Dateien
- übersprungene Dateien samt Grund

## Empfehlung

Für normale ChatGPT-Analysen reicht:

```bash
php tools/export-treeforge-for-chatgpt.php
```

Für schnelle kleine Uploads:

```bash
php tools/export-treeforge-for-chatgpt.php --minimal
```