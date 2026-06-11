<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Core/bootstrap.php';

use TreeForge\Modules\Archives\ArchivesController;

$root = dirname(__DIR__, 2);

echo (new ArchivesController($root))->handle();