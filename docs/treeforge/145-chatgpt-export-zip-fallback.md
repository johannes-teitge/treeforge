# Patch 145 – ChatGPT Export ZIP Fallback

## Problem

Auf manchen Laragon/PHP-Installationen ist `ZipArchive` nicht aktiv.
Dann brach das Export-Tool ab mit:

```text
FEHLER: PHP-Erweiterung ZipArchive ist nicht aktiv.
```

## Lösung

Das Export-Tool arbeitet nun zweistufig:

1. Wenn `ZipArchive` verfügbar ist, wird es verwendet.
2. Wenn `ZipArchive` nicht verfügbar ist, nutzt Windows automatisch PowerShell `Compress-Archive`.

## Nutzung

```bash
php tools/export-treeforge-for-chatgpt.php
```

Dry Run:

```bash
php tools/export-treeforge-for-chatgpt.php --dry-run
```

Minimaler Export:

```bash
php tools/export-treeforge-for-chatgpt.php --minimal
```

## Hinweis

Trotz Fallback ist es sinnvoll, in Laragon `ext-zip` zu aktivieren:

```text
Laragon → PHP → Extensions → zip
```

Danach Webserver neu starten.