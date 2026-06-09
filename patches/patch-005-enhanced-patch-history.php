<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 005
 * Enhanced Patch History
 *
 * Verbessert patches/run.php:
 * - pro Patch werden alle Log-Meldungen gesammelt
 * - Dauer wird gemessen
 * - Ergebnis wird in storage/system/patch-history.json gespeichert
 * - executed.json bleibt für schnelle Skip-Prüfung erhalten
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 005 Enhanced Patch History gestartet');

    $write($root . '/patches/run.php', <<<'PHP'
<?php
declare(strict_types=1);

$patchDir = __DIR__;
$root = dirname(__DIR__);

$executedFile = $patchDir . '/executed.json';
$historyFile = $root . '/storage/system/patch-history.json';
$logFile = $root . '/storage/logs/patch-runner.log';

function runnerLog(string $message): void
{
    global $logFile;

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;

    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0775, true);
    }

    file_put_contents($logFile, $line, FILE_APPEND);
}

function loadJsonFile(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveJsonFile(string $file, array $data): void
{
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }

    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

$executed = loadJsonFile($executedFile);
$history = loadJsonFile($historyFile);

$patches = glob($patchDir . '/patch-*.php') ?: [];
sort($patches);

runnerLog('Patch Runner gestartet');

foreach ($patches as $patchFile) {
    $patchName = basename($patchFile);

    if (isset($executed[$patchName])) {
        runnerLog("Übersprungen: {$patchName}");
        continue;
    }

    $messages = [];
    $startedAt = microtime(true);

    $patchLog = function (string $message) use (&$messages): void {
        $messages[] = [
            'time' => date('c'),
            'message' => $message,
        ];

        runnerLog($message);
    };

    runnerLog("Starte: {$patchName}");

    $patch = require $patchFile;

    if (!is_callable($patch)) {
        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

        $history[$patchName] = [
            'executed_at' => date('c'),
            'status' => 'error',
            'duration_ms' => $durationMs,
            'error' => 'Patch gibt keine callable Funktion zurück',
            'messages' => $messages,
        ];

        saveJsonFile($historyFile, $history);

        runnerLog("FEHLER: {$patchName} gibt keine Funktion zurück");
        exit(1);
    }

    try {
        $patch($root, $patchLog);

        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

        $executed[$patchName] = [
            'executed_at' => date('c'),
            'status' => 'ok',
        ];

        $history[$patchName] = [
            'executed_at' => date('c'),
            'status' => 'ok',
            'duration_ms' => $durationMs,
            'messages' => $messages,
        ];

        saveJsonFile($executedFile, $executed);
        saveJsonFile($historyFile, $history);

        runnerLog("Fertig: {$patchName} ({$durationMs} ms)");
    } catch (Throwable $e) {
        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

        $history[$patchName] = [
            'executed_at' => date('c'),
            'status' => 'error',
            'duration_ms' => $durationMs,
            'error' => $e->getMessage(),
            'messages' => $messages,
        ];

        saveJsonFile($historyFile, $history);

        runnerLog("FEHLER in {$patchName}: " . $e->getMessage());
        exit(1);
    }
}

runnerLog('Patch Runner beendet');
PHP);

    $write($root . '/docs/patch-system.md', <<<'MD'
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

MD);

    $log('Patch 005 Enhanced Patch History fertig');
};
