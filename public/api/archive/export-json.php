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