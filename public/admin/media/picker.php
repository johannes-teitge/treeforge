<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\Media\MediaCategoryRepository;
use TreeForge\Modules\Media\MediaRepository;

$root = dirname(__DIR__, 3);
$repo = new MediaRepository($root);
$categories = new MediaCategoryRepository($root);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bytesHuman(int|float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, 2) . ' ' . $units[$i];
}

$filter = (string)($_GET['category'] ?? 'all');
$items = $repo->all();

if ($filter !== 'all') {
    $items = array_values(array_filter($items, static function (array $item) use ($filter): bool {
        $category = (string)($item['category'] ?? '');

        if ($filter === '_none') {
            return $category === '';
        }

        return $category === $filter;
    }));
}

$allItems = $repo->all();
$counts = $categories->counts($allItems);

header('Content-Type: text/html; charset=utf-8');

echo '<div class="tf-picker-shell">';
echo '<aside class="tf-picker-sidebar">';
echo '<h3>Medien</h3>';
echo '<button type="button" class="tf-picker-filter' . ($filter === 'all' ? ' active' : '') . '" data-picker-category="all">Alle <span>' . e(count($allItems)) . '</span></button>';
echo '<button type="button" class="tf-picker-filter' . ($filter === '_none' ? ' active' : '') . '" data-picker-category="_none">Nicht einsortiert <span>' . e($counts[''] ?? 0) . '</span></button>';
echo '<h3>Kategorien</h3>';

foreach ($categories->all() as $category) {
    $id = (string)($category['id'] ?? '');
    echo '<button type="button" class="tf-picker-filter' . ($filter === $id ? ' active' : '') . '" data-picker-category="' . e($id) . '">'
        . e($category['label'] ?? $id)
        . ' <span>' . e($counts[$id] ?? 0) . '</span>'
        . '</button>';
}

echo '</aside>';

echo '<section class="tf-picker-content">';
echo '<div class="tf-picker-toolbar">';
echo '<strong>' . e(count($items)) . ' Medien</strong>';
echo '<span>Medium auswählen und übernehmen.</span>';
echo '</div>';

if ($items === []) {
    echo '<div class="tf-media-empty">Keine Medien gefunden.</div>';
} else {
    echo '<div class="tf-picker-grid">';

    foreach ($items as $item) {
        $url = method_exists($repo, 'publicUrlWithVersion') ? $repo->publicUrlWithVersion($item) : $repo->publicUrl($item);
        $plainUrl = $repo->publicUrl($item);
        $kind = (string)($item['kind'] ?? '');
        $isImage = in_array($kind, ['image', 'vector'], true);

        $preview = $isImage
            ? '<img src="' . e($url) . '" alt="' . e((string)($item['alt'] ?? '')) . '">'
            : '<div class="tf-media-file-preview">' . e(strtoupper((string)($item['extension'] ?? 'FILE'))) . '</div>';

        $payload = [
            'id' => (string)($item['id'] ?? ''),
            'title' => (string)($item['title'] ?? ''),
            'alt' => (string)($item['alt'] ?? ''),
            'filename' => (string)($item['filename'] ?? ''),
            'relative_path' => (string)($item['relative_path'] ?? ''),
            'url' => $plainUrl,
            'preview_url' => $url,
            'kind' => $kind,
            'mime' => (string)($item['mime'] ?? ''),
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'size' => $item['size'] ?? null,
            'category' => (string)($item['category'] ?? ''),
        ];

        echo '<button type="button" class="tf-picker-item" data-media=\'' . e(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '\'>'
            . '<span class="tf-picker-preview">' . $preview . '</span>'
            . '<span class="tf-picker-info">'
            . '<strong>' . e($item['title'] ?? $item['filename'] ?? '') . '</strong>'
            . '<small>' . e($item['filename'] ?? '') . '</small>'
            . '<small>' . e(($item['width'] ?? '-') . ' × ' . ($item['height'] ?? '-') . ' · ' . bytesHuman((int)($item['size'] ?? 0))) . '</small>'
            . '</span>'
            . '</button>';
    }

    echo '</div>';
}

echo '</section>';
echo '</div>';