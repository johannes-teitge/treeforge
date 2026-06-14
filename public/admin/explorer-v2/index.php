<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\ExplorerV2\ExplorerV2Controller;

$root = dirname(__DIR__, 3);

echo (new ExplorerV2Controller($root))->handle();