<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/bootstrap.php';

use TreeForge\Core\Config;
use TreeForge\Core\Workspace;
use TreeForge\Renderer\HtmlRenderer;

$root = dirname(__DIR__);

$config = new Config($root . '/storage/config/app.json');

/**
 * Standard: öffentliche Website liest nur published.
 * Preview lokal: ?workspace=draft oder ?workspace=review
 */
$workspaceName = $_GET['workspace'] ?? Workspace::PUBLISHED;

$workspace = new Workspace($root, $workspaceName);
$page = $workspace->loadPage('home');

$renderer = new HtmlRenderer();

echo $renderer->render($page, $config);