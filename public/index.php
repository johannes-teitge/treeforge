<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TreeForge\Core\Config;
use TreeForge\Core\Page;
use TreeForge\Renderer\HtmlRenderer;

$root = dirname(__DIR__);

$config = new Config($root . '/storage/config/app.json');
$page = new Page($root . '/storage/pages/home.json');

$renderer = new HtmlRenderer();

echo $renderer->render($page, $config);