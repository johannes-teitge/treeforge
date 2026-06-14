<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/bootstrap.php';

use TreeForge\Modules\Frontend\PageRenderer;

$root = dirname(__DIR__);
echo (new PageRenderer($root))->render(
    (string)($_GET['page'] ?? 'home'),
    (string)($_GET['workspace'] ?? 'draft'),
    (string)($_GET['template'] ?? 'default')
);