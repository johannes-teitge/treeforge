<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/bootstrap.php';

use TreeForge\Core\ArchiveManager;
use TreeForge\Core\Config;
use TreeForge\Core\Workspace;
use TreeForge\Renderer\HtmlRenderer;

$root = dirname(__DIR__);

$config = new Config($root . '/storage/config/app.json');

/**
 * Standard: öffentliche Website liest nur published.
 * Preview lokal: ?workspace=draft oder ?workspace=review
 * Archive Frontend Preview: ?archive=<version>&page=home
 */
$workspaceName = (string)($_GET['workspace'] ?? Workspace::PUBLISHED);
$pageId = (string)($_GET['page'] ?? 'home');
$archiveVersion = (string)($_GET['archive'] ?? '');

if ($archiveVersion !== '') {
    $archive = new ArchiveManager($root);
    $pageData = $archive->loadVersion($pageId, $archiveVersion);

    $previewDir = $root . '/storage/cache/archive-preview';

    if (!is_dir($previewDir)) {
        mkdir($previewDir, 0775, true);
    }

    $previewFile = $previewDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $pageId . '-' . $archiveVersion) . '.json';

    file_put_contents(
        $previewFile,
        json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    $page = new \TreeForge\Core\Page($previewFile);
} else {
    $workspace = new Workspace($root, $workspaceName);
    $page = $workspace->loadPage($pageId);
}

$renderer = new HtmlRenderer();

echo $renderer->render($page, $config);