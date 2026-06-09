# TreeForge Patch System

TreeForge verwendet ein einfaches Patch-System für wiederholbare Projektänderungen.

## Struktur

```text
patches/
├─ run.php
├─ executed.json
├─ patch-003-image-node.php
├─ patch-004-brand-assets.php
└─ patch-005-enhanced-patch-history.php

storage/
├─ logs/
│  └─ patch-runner.log
└─ system/
   └─ patch-history.json
```

## Ausführen

```bash
php patches/run.php
```

## executed.json

Diese Datei merkt sich kurz, welche Patches bereits erfolgreich ausgeführt wurden.

## patch-history.json

Diese Datei enthält die ausführliche Historie mit:

- Status
- Ausführungszeit
- Dauer in Millisekunden
- Meldungen des Patches
- Fehlertext bei Problemen

## Warum zwei Dateien?

- `executed.json` ist klein und schnell.
- `patch-history.json` ist ausführlich und dient zur Nachverfolgung.

## Regeln für neue Patches

Jeder Patch gibt eine Funktion zurück:

```php
<?php
declare(strict_types=1);

return function (string $root, callable $log): void {
    $log('Patch gestartet');

    // Änderungen durchführen

    $log('Patch fertig');
};
```

## Git-Regel

Nach erfolgreichem Patch und Test:

```bash
git add .
git commit -m "Beschreibung"
git tag v0.1.x-alpha
```
