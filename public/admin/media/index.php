<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Settings\SettingsManager;
use TreeForge\Modules\Media\MediaCategoryRepository;
use TreeForge\Modules\Media\MediaRepository;

$root = dirname(__DIR__, 3);
$settings = new SettingsManager($root);
$data = $settings->all();

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

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    try {
        $categories->create(
            (string)($_POST['category_label'] ?? ''),
            (string)($_POST['category_description'] ?? '')
        );
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
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
$categoryList = $categories->all();

$totalSize = array_sum(array_map(static fn(array $item): int => (int)($item['size'] ?? 0), $allItems));
$imageCount = count(array_filter($allItems, static fn(array $item): bool => in_array((string)($item['kind'] ?? ''), ['image', 'vector'], true)));
$downloadCount = count(array_filter($allItems, static fn(array $item): bool => in_array((string)($item['kind'] ?? ''), ['document', 'download'], true)));

$sidebar = '<aside class="tf-media-sidebar">'
    . '<h3>Bilder anzeigen</h3>'
    . '<nav class="tf-media-filter">'
    . '<a class="' . ($filter === 'all' ? 'active' : '') . '" href="/admin/media/"><span>Alle Medien</span><span class="tf-media-count">' . e(count($allItems)) . '</span></a>'
    . '<a class="' . ($filter === '_none' ? 'active' : '') . '" href="/admin/media/?category=_none" data-category-drop=""><span>Nicht einsortiert</span><span class="tf-media-count">' . e($counts[''] ?? 0) . '</span></a>'
    . '</nav>'
    . '<h3>Kategorien</h3>'
    . '<nav class="tf-media-filter">';

foreach ($categoryList as $category) {
    $id = (string)($category['id'] ?? '');
    $sidebar .= '<div class="tf-media-category-row">'
        . '<a class="' . ($filter === $id ? 'active' : '') . '" href="/admin/media/?category=' . e($id) . '" data-category-drop="' . e($id) . '">'
        . '<span>' . e($category['label'] ?? $id) . '</span>'
        . '<span class="tf-media-count">' . e($counts[$id] ?? 0) . '</span>'
        . '</a>'
        . '<a class="tf-media-category-edit" href="/admin/media/category.php?id=' . e($id) . '" title="Kategorie bearbeiten">✎</a>'
        . '</div>';
}

$sidebar .= '</nav>'
    . '<form method="post" class="tf-media-category-form">'
    . '<h3>Neue Kategorie</h3>'
    . '<label><span>Name</span><input type="text" name="category_label" placeholder="z. B. Produkte"></label>'
    . '<label><span>Beschreibung</span><input type="text" name="category_description" placeholder="optional"></label>'
    . '<button class="tf-admin-button secondary" type="submit" name="create_category" value="1">Kategorie anlegen</button>'
    . '</form>'
    . '<div class="tf-media-stats">'
    . '<div class="tf-media-stat"><span>Bilder</span><strong>' . e($imageCount) . '</strong></div>'
    . '<div class="tf-media-stat"><span>Downloads</span><strong>' . e($downloadCount) . '</strong></div>'
    . '<div class="tf-media-stat"><span>Speicher</span><strong>' . e(bytesHuman($totalSize)) . '</strong></div>'
    . '</div>'
    . '</aside>';

$grid = '<section class="tf-media-panel">'
    . '<div class="tf-media-toolbar">'
    . '<div><h2>Medien</h2><p>' . e(count($items)) . ' Einträge angezeigt.</p></div>'
    . '</div>';

if ($items === []) {
    $grid .= '<div class="tf-media-empty">Keine Medien gefunden.</div>';
} else {
    $grid .= '<div class="tf-media-grid">';

    foreach ($items as $item) {
        $url = method_exists($repo, 'publicUrlWithVersion') ? $repo->publicUrlWithVersion($item) : $repo->publicUrl($item);
        $kind = (string)($item['kind'] ?? '');
        $isImage = in_array($kind, ['image', 'vector'], true);
        $categoryLabel = $item['category'] !== '' ? $categories->labelFor((string)$item['category']) : 'Nicht einsortiert';

        $preview = $isImage
            ? '<img src="' . e($url) . '" alt="' . e((string)($item['alt'] ?? '')) . '">'
            : '<div class="tf-media-file-preview">' . e(strtoupper((string)($item['extension'] ?? 'FILE'))) . '</div>';

        $grid .= '<article class="tf-media-card" draggable="true" data-media-id="' . e((string)($item['id'] ?? '')) . '">'
            . '<div class="tf-media-preview">' . $preview . '</div>'
            . '<div class="tf-media-info">'
            . '<strong>' . e($item['title'] ?? $item['filename'] ?? '') . '</strong>'
            . '<small>' . e($item['filename'] ?? '') . '</small>'
            . '<small>' . e(($item['width'] ?? '-') . ' × ' . ($item['height'] ?? '-') . ' · ' . bytesHuman((int)($item['size'] ?? 0))) . '</small>'
            . '<small>Kategorie: ' . e($categoryLabel) . '</small>'
            . '</div>'
            . '<div class="tf-media-actions">'
            . '<a class="tf-media-button secondary" href="' . e($repo->publicUrl($item)) . '" target="_blank" rel="noopener">Ansehen</a>'
            . '<a class="tf-media-button secondary" href="/admin/media/edit.php?id=' . e((string)($item['id'] ?? '')) . '">Bearbeiten</a>'
            . '</div>'
            . '</article>';
    }

    $grid .= '</div>';
}

$grid .= '</section>';

$content = '';

if ($saved) {
    $content .= '<div class="tf-notice success">Kategorie wurde angelegt.</div>';
}

if ($error !== '') {
    $content .= '<div class="tf-notice error">' . e($error) . '</div>';
}

$content .= '<section class="tf-media-upload-zone" id="tf-media-upload-zone">'
    . '<div class="tf-media-upload-icon">↑</div>'
    . '<div><h3>Bilder und Dateien hochladen</h3><p>Dateien hierher ziehen oder über den Button auswählen. Regeln kommen aus den Media Settings.</p><div id="tf-media-upload-result" class="tf-media-upload-result"></div></div>'
    . '<div><input type="file" id="tf-media-upload-input" multiple hidden><button type="button" class="tf-admin-button" id="tf-media-upload-select">Dateien auswählen</button></div>'
    . '</section>';

$content .= '<section class="tf-media-grid-shell">' . $sidebar . $grid . '</section>';

echo (new AdminLayout())->render(
    'Media',
    $content,
    'media',
    [
        'site_name' => (string)($data['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Medienbibliothek',
    ]
);

echo '<script src="/assets/js/media-upload.js"></script>';
echo '<script src="/assets/js/media-categories-dnd.js"></script>';