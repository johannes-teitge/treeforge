<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\Media\MediaUploadService;

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 3);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Nur POST erlaubt.');
    }

    if (empty($_FILES['files'])) {
        throw new RuntimeException('Keine Dateien empfangen.');
    }

    $service = new MediaUploadService($root);
    $result = [];

    $files = $_FILES['files'];

    if (is_array($files['name'])) {
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $result[] = $service->upload([
                'name' => $files['name'][$i],
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ]);
        }
    } else {
        $result[] = $service->upload($files);
    }

    echo json_encode([
        'ok' => true,
        'files' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}