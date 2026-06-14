<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Export for ChatGPT
 *
 * Erstellt ein sauberes Projekt-ZIP ohne vendor, .git, Logs, große Binärdaten usw.
 *
 * Nutzung:
 *   php tools/export-treeforge-for-chatgpt.php
 *   php tools/export-treeforge-for-chatgpt.php --dry-run
 *   php tools/export-treeforge-for-chatgpt.php --minimal
 */

$root = dirname(__DIR__);
$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$minimal = in_array('--minimal', $args, true);

function out(string $message): void
{
    echo $message . PHP_EOL;
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function relPath(string $root, string $file): string
{
    $root = rtrim(normalizePath(realpath($root) ?: $root), '/') . '/';
    $file = normalizePath(realpath($file) ?: $file);

    if (str_starts_with($file, $root)) {
        return substr($file, strlen($root));
    }

    return basename($file);
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
}

function shouldExclude(string $relative, bool $minimal): bool
{
    $relative = normalizePath($relative);
    $base = basename($relative);

    $blockedExact = [
        '.env',
        '.env.local',
        '.env.production',
        'treeforge.zip',
    ];

    if (in_array($base, $blockedExact, true)) {
        return true;
    }

    $blockedPrefixes = [
        '.git/',
        'vendor/',
        'node_modules/',
        'exports/',
        'storage/logs/',
        'storage/legacy/',
        'storage/cache/',
        'storage/tmp/',
    ];

    foreach ($blockedPrefixes as $prefix) {
        if (str_starts_with($relative, $prefix)) {
            return true;
        }
    }

    $blockedSuffixes = [
        '.zip', '.7z', '.rar', '.tar', '.gz',
        '.sql', '.sqlite', '.sqlite3', '.db',
        '.bak', '.key', '.pem', '.p12', '.pfx', '.crt',
        '.log', '.lnk',
    ];

    foreach ($blockedSuffixes as $suffix) {
        if (str_ends_with(strtolower($relative), $suffix)) {
            return true;
        }
    }

    if (preg_match('/\.bak-\d{8}-\d{6}$/', $relative)) {
        return true;
    }

    if ($minimal) {
        $allowedPrefixes = [
            'app/',
            'core/',
            'public/',
            'patches/',
            'docs/treeforge/',
            'tools/',
            'storage/workspaces/',
        ];

        $allowedFiles = [
            'composer.json',
            'composer.lock',
            '.gitignore',
            'README.md',
        ];

        if (in_array($relative, $allowedFiles, true)) {
            return false;
        }

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return false;
            }
        }

        return true;
    }

    return false;
}

function collectFiles(string $root, bool $minimal): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $relative = relPath($root, $path);

        if (shouldExclude($relative, $minimal)) {
            continue;
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            continue;
        }

        $files[] = [
            'absolute' => $path,
            'relative' => $relative,
            'size' => $file->getSize(),
        ];
    }

    usort($files, static fn (array $a, array $b): int => strcmp($a['relative'], $b['relative']));

    return $files;
}

function createZipWithZipArchive(string $zipFile, array $files): void
{
    $zip = new ZipArchive();
    $result = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($result !== true) {
        throw new RuntimeException('ZIP konnte nicht erstellt werden: ' . $zipFile);
    }

    foreach ($files as $file) {
        $zip->addFile($file['absolute'], $file['relative']);
    }

    $zip->close();
}

function psQuote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

function createZipWithPowerShell(string $root, string $zipFile, array $files): void
{
    $tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'treeforge-chatgpt-export-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    ensureDir($tmpBase);

    try {
        foreach ($files as $file) {
            $target = $tmpBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['relative']);
            ensureDir(dirname($target));
            if (!copy($file['absolute'], $target)) {
                throw new RuntimeException('Konnte Datei nicht in Export-Staging kopieren: ' . $file['relative']);
            }
        }

        $ps1 = $tmpBase . DIRECTORY_SEPARATOR . 'compress-export.ps1';
        $script = "\$ErrorActionPreference = 'Stop'\n"
            . "\$source = " . psQuote($tmpBase) . "\n"
            . "\$destination = " . psQuote($zipFile) . "\n"
            . "if (Test-Path \$destination) { Remove-Item \$destination -Force }\n"
            . "Compress-Archive -Path (Join-Path \$source '*') -DestinationPath \$destination -Force\n";

        file_put_contents($ps1, $script, LOCK_EX);

        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($ps1);
        exec($cmd, $out, $code);

        if ($code !== 0 || !file_exists($zipFile)) {
            throw new RuntimeException(
                "PowerShell Compress-Archive fehlgeschlagen.\n" . implode("\n", $out)
            );
        }
    } finally {
        removeDir($tmpBase);
    }
}

$files = collectFiles($root, $minimal);
$totalBytes = array_sum(array_column($files, 'size'));

out('TreeForge ChatGPT Export');
out('Modus: ' . ($minimal ? 'minimal' : 'normal') . ($dryRun ? ' / DRY RUN' : ''));
out('Dateien: ' . count($files));
out('Größe: ' . number_format($totalBytes / 1024 / 1024, 2, ',', '.') . ' MB');

if ($dryRun) {
    foreach ($files as $file) {
        out(' - ' . $file['relative']);
    }
    exit(0);
}

$exportDir = $root . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'chatgpt';
ensureDir($exportDir);

$zipFile = $exportDir . DIRECTORY_SEPARATOR . 'treeforge-chatgpt-' . date('Ymd-His') . '.zip';

try {
    if (class_exists('ZipArchive')) {
        createZipWithZipArchive($zipFile, $files);
        out('ZIP erstellt mit ZipArchive.');
    } else {
        out('Hinweis: ZipArchive ist nicht aktiv. Nutze PowerShell Compress-Archive als Fallback.');
        createZipWithPowerShell($root, $zipFile, $files);
        out('ZIP erstellt mit PowerShell Compress-Archive.');
    }

    out('Fertig: ' . $zipFile);
} catch (Throwable $e) {
    out('FEHLER: ' . $e->getMessage());
    out('Tipp: In Laragon kann ext-zip meist über Menü → PHP → Extensions → zip aktiviert werden. Danach Apache/Nginx neu starten.');
    exit(1);
}