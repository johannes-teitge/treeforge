<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/bootstrap.php';

use TreeForge\Core\ArchiveManager;
use TreeForge\Core\Config;
use TreeForge\Core\Workspace;
use TreeForge\Renderer\HtmlRenderer;
use TreeForge\Renderer\TwigPageRenderer;

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

$rendererMode = strtolower(trim((string)($_GET['renderer'] ?? getenv('TREEFORGE_RENDERER') ?: 'twig')));
$twigAvailable = class_exists(TwigPageRenderer::class) && class_exists(\Twig\Environment::class);

if ($rendererMode === 'legacy') {
    $renderer = new HtmlRenderer();
    echo $renderer->render($page, $config);
    return;
}

if (!$twigAvailable) {
    echo "<!-- TreeForge: Twig nicht verfügbar, Legacy-Fallback aktiv -->\n";
    $renderer = new HtmlRenderer();
    echo $renderer->render($page, $config);
    return;
}

try {
    $renderer = new TwigPageRenderer($root, $workspaceName);
    echo $renderer->render($page, $config);
    return;
} catch (\Throwable $e) {
    if ($rendererMode === 'twig-strict' || getenv('TREEFORGE_RENDERER_STRICT') === '1') {
        throw $e;
    }

    echo "<!-- TreeForge Twig fallback: "
        . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . " -->\n";

    $renderer = new HtmlRenderer();
    echo $renderer->render($page, $config);
}