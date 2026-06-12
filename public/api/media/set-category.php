<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\Media\MediaCategoryRepository;
use TreeForge\Modules\Media\MediaRepository;

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 3);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Nur POST erlaubt.');
    }

    $id = trim((string)($_POST['id'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));

    if ($id === '') {
        throw new RuntimeException('Media-ID fehlt.');
    }

    $repo = new MediaRepository($root);
    $categories = new MediaCategoryRepository($root);

    $item = $repo->findById($id);

    if (!$item) {
        throw new RuntimeException('Medium nicht gefunden.');
    }

    if ($category !== '' && !$categories->find($category)) {
        throw new RuntimeException('Kategorie nicht gefunden.');
    }

    $item['category'] = $category;
    $repo->save($item);

    echo json_encode([
        'ok' => true,
        'id' => $id,
        'category' => $category,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}