<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\ExplorerV3\ExplorerV3Controller;

$root = dirname(__DIR__, 3);

echo (new ExplorerV3Controller($root))->handle();