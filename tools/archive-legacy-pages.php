<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);
$legacyDir = $root . '/storage/pages';
$targetBase = $root . '/storage/legacy';
$targetDir = $targetBase . '/pages-' . date('Ymd-His');

function logLine(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

logLine('TreeForge Legacy Pages Archivierung gestartet' . ($dryRun ? ' (DRY RUN)' : ''));

if (!is_dir($legacyDir)) {
    logLine('Kein Legacy-Ordner vorhanden: storage/pages');
    exit(0);
}

$workspaceFiles = glob($root . '/storage/workspaces/*/pages/*.json') ?: [];
if ($workspaceFiles === []) {
    logLine('ABBRUCH: Keine Workspace-Page-Dateien gefunden. Legacy wird nicht archiviert.');
    exit(1);
}

logLine('Workspace-Page-Dateien gefunden: ' . count($workspaceFiles));
logLine('Legacy-Ordner: ' . $legacyDir);
logLine('Ziel-Archiv: ' . $targetDir);

if ($dryRun) {
    foreach (glob($legacyDir . '/*') ?: [] as $file) {
        logLine('Würde archivieren: ' . $file);
    }
    logLine('Dry Run fertig. Keine Änderung vorgenommen.');
    exit(0);
}

if (!is_dir($targetBase)) {
    mkdir($targetBase, 0775, true);
}

if (!rename($legacyDir, $targetDir)) {
    logLine('ABBRUCH: storage/pages konnte nicht verschoben werden.');
    exit(1);
}

logLine('Legacy-Ordner wurde archiviert.');
logLine('Neue Lage: ' . $targetDir);
logLine('Fertig.');