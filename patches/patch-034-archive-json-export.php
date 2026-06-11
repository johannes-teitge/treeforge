<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 034
 * Archive JSON Export
 *
 * Ziel:
 * - Archivversionen im Archive Center als JSON herunterladen
 * - eigener API-Download-Endpunkt
 * - Export enthält Metadaten und archivierte Page-Daten
 *
 * Dateien:
 * - public/api/archive/export-json.php
 * - app/Modules/Archives/ArchivesRenderer.php
 * - docs/archive-json-export.md
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

    $log('Patch 034 Archive JSON Export gestartet');

    $write($root . '/public/api/archive/export-json.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Core\ArchiveManager;

$root = dirname(__DIR__, 3);

$page = trim((string)($_GET['page'] ?? 'home'));
$version = trim((string)($_GET['version'] ?? ''));

if ($page === '') {
    $page = 'home';
}

if ($version === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => false,
        'error' => 'Missing archive version.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $page)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => false,
        'error' => 'Invalid page id.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{6}$/', $version)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => false,
        'error' => 'Invalid archive version.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $archive = new ArchiveManager($root);
    $pageData = $archive->loadVersion($page, $version);

    $export = [
        'treeforge_export' => [
            'type' => 'archive-json',
            'format_version' => '1.0.0',
            'exported_at' => date('c'),
            'page' => $page,
            'archive_version' => $version,
            'source' => 'TreeForge Archive Center',
        ],
        'page' => $pageData,
    ];

    $filename = 'treeforge-archive-' . $page . '-' . $version . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
PHP);

    $rendererFile = $root . '/app/Modules/Archives/ArchivesRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        $old = <<<'PHP'
            $html .= '<a class="tf-archive-button small" href="/?archive=' . $idUrl . '&page=' . $pageUrl . '" target="_blank" rel="noopener">Ansehen</a>';
            $html .= '<a class="tf-archive-button small secondary" href="/explorer?archive=' . $idUrl . '&page=' . $pageUrl . '">Im Explorer</a>';
            $html .= '<button type="button" class="tf-archive-button small danger" data-archive-restore="' . $idSafe . '" data-page="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '">Wiederherstellen</button>';
PHP;

        $new = <<<'PHP'
            $html .= '<a class="tf-archive-button small" href="/?archive=' . $idUrl . '&page=' . $pageUrl . '" target="_blank" rel="noopener">Ansehen</a>';
            $html .= '<a class="tf-archive-button small secondary" href="/explorer?archive=' . $idUrl . '&page=' . $pageUrl . '">Im Explorer</a>';
            $html .= '<a class="tf-archive-button small secondary" href="/api/archive/export-json.php?version=' . $idUrl . '&page=' . $pageUrl . '">JSON Export</a>';
            $html .= '<button type="button" class="tf-archive-button small danger" data-archive-restore="' . $idSafe . '" data-page="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '">Wiederherstellen</button>';
PHP;

        if (str_contains($renderer, $old)) {
            $renderer = str_replace($old, $new, $renderer);
            $write($rendererFile, $renderer);
        } else {
            $log('Hinweis: Aktionen-Block in ArchivesRenderer nicht gefunden, keine Änderung vorgenommen');
        }
    }

    $write($root . '/docs/archive-json-export.md', <<<'MD'
# Archive JSON Export

Patch 034 ergänzt den JSON-Export für Archivversionen.

## Route

```text
/api/archive/export-json.php?version=<version>&page=home
```

## Verwendung

Im Archive Center gibt es pro Archivversion einen Button:

```text
JSON Export
```

Der Browser lädt eine Datei herunter:

```text
treeforge-archive-home-2026-06-11-123456.json
```

## Exportformat

```json
{
  "treeforge_export": {
    "type": "archive-json",
    "format_version": "1.0.0",
    "exported_at": "2026-06-11T10:00:00+02:00",
    "page": "home",
    "archive_version": "2026-06-11-123456",
    "source": "TreeForge Archive Center"
  },
  "page": {}
}
```

## Hinweis

Dieser Export enthält nur JSON-Daten.

Medien werden noch nicht mit exportiert. Dafür ist später ein ZIP-Export mit Manifest und Media-Mapping vorgesehen.
MD);

    $log('Patch 034 Archive JSON Export fertig');
};
