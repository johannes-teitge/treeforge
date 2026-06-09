<?php
declare(strict_types=1);

$patchDir = __DIR__;
$root = dirname(__DIR__);
$executedFile = $patchDir . '/executed.json';
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

function loadExecuted(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveExecuted(string $file, array $data): void
{
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

$executed = loadExecuted($executedFile);
$patches = glob($patchDir . '/patch-*.php') ?: [];
sort($patches);

runnerLog('Patch Runner gestartet');

foreach ($patches as $patchFile) {
    $patchName = basename($patchFile);

    if (isset($executed[$patchName])) {
        runnerLog("Übersprungen: {$patchName}");
        continue;
    }

    runnerLog("Starte: {$patchName}");

    $patch = require $patchFile;

    if (!is_callable($patch)) {
        runnerLog("FEHLER: {$patchName} gibt keine Funktion zurück");
        exit(1);
    }

    try {
        $patch($root, 'runnerLog');

        $executed[$patchName] = [
            'executed_at' => date('c'),
            'status' => 'ok'
        ];

        saveExecuted($executedFile, $executed);
        runnerLog("Fertig: {$patchName}");
    } catch (Throwable $e) {
        runnerLog("FEHLER in {$patchName}: " . $e->getMessage());
        exit(1);
    }
}

runnerLog('Patch Runner beendet');