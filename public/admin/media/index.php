<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Settings\SettingsManager;
use TreeForge\Modules\Media\MediaManager;

$root = dirname(__DIR__, 3);
$settings = new SettingsManager($root);
$settingsData = $settings->all();

$media = new MediaManager($root);
$items = $media->all();
$categories = $media->categories($items);
$stats = $media->stats($items);

$currentCategory = trim((string)($_GET['category'] ?? 'all'));

if ($currentCategory !== 'all') {
    $items = array_values(array_filter($items, static function (array $item) use ($currentCategory): bool {
        $category = trim((string)($item['category'] ?? ''));
        $category = $category === '' ? 'Nicht einsortiert' : $category;
        return $category === $currentCategory;
    }));
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatBytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $power = min((int)floor(log($bytes, 1024)), count($units) - 1);

    return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
}

$nav = '<a class="' . ($currentCategory === 'all' ? 'active' : '') . '" href="/admin/media/"><span>Alle Medien</span><span class="tf-media-count">' . (int)$stats['count'] . '</span></a>';

foreach ($categories as $category => $count) {
    $nav .= '<a class="' . ($currentCategory === $category ? 'active' : '') . '" href="/admin/media/?category=' . rawurlencode((string)$category) . '">'
        . '<span>' . e($category) . '</span>'
        . '<span class="tf-media-count">' . (int)$count . '</span>'
        . '</a>';
}

$cards = '';

if ($items === []) {
    $cards = '<div class="tf-media-empty">Noch keine Medien gefunden. Lege Dateien testweise unter <code>storage/media/originals/</code> ab.</div>';
} else {
    foreach ($items as $item) {
        $title = trim((string)($item['title'] ?? ''));
        $filename = (string)($item['filename'] ?? '');
        $url = (string)($item['url'] ?? '');
        $mime = (string)($item['mime'] ?? '');
        $width = $item['width'] ?? null;
        $height = $item['height'] ?? null;
        $size = formatBytes((int)($item['size'] ?? 0));
        $dims = ($width && $height) ? ((int)$width . ' × ' . (int)$height . ' px') : 'Dimension unbekannt';

        $cards .= '<article class="tf-media-card">'
            . '<div class="tf-media-preview">'
            . '<img src="' . e($url) . '" alt="' . e((string)($item['alt'] ?? $title)) . '">'
            . '</div>'
            . '<div class="tf-media-info">'
            . '<strong>' . e($title !== '' ? $title : $filename) . '</strong>'
            . '<small>' . e($filename) . '</small>'
            . '<small>' . e($mime ?: 'unknown') . ' · ' . e($dims) . ' · ' . e($size) . '</small>'
            . '<small>ID: <code>' . e($item['id'] ?? '') . '</code></small>'
            . '</div>'
            . '<div class="tf-media-actions">'
            . '<a class="tf-media-button secondary" href="' . e($url) . '" target="_blank" rel="noopener">Ansehen</a>'
            . '<button type="button" class="tf-media-button secondary" onclick="navigator.clipboard.writeText(\'' . e($url) . '\')">URL kopieren</button>'
            . '</div>'
            . '</article>';
    }
}

$content = ''
    . '<section class="tf-media-toolbar">'
    . '<div><h2>Medienbibliothek</h2><p>Originale, Metadaten und späterer Render-Cache.</p></div>'
    . '<div class="tf-admin-actions"><a class="tf-admin-button secondary" href="/admin/settings/">Media Settings später</a></div>'
    . '</section>'
    . '<section class="tf-media-grid-shell">'
    . '<aside class="tf-media-sidebar">'
    . '<h3>Bilder anzeigen</h3>'
    . '<nav class="tf-media-filter">' . $nav . '</nav>'
    . '<div class="tf-media-stats">'
    . '<div class="tf-media-stat"><span>Medien</span><strong>' . (int)$stats['count'] . '</strong></div>'
    . '<div class="tf-media-stat"><span>Kategorien</span><strong>' . (int)$stats['categories'] . '</strong></div>'
    . '<div class="tf-media-stat"><span>Originale</span><strong>' . e(formatBytes((int)$stats['total_size'])) . '</strong></div>'
    . '<div class="tf-media-stat"><span>Cache</span><strong>' . e(formatBytes((int)$stats['cache_size'])) . '</strong></div>'
    . '</div>'
    . '</aside>'
    . '<main class="tf-media-panel">'
    . '<h3>' . e($currentCategory === 'all' ? 'Alle Medien' : $currentCategory) . '</h3>'
    . '<div class="tf-media-grid">' . $cards . '</div>'
    . '</main>'
    . '</section>';

echo (new AdminLayout())->render(
    'Media',
    $content,
    'media',
    [
        'site_name' => (string)($settingsData['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Medienverwaltung · Foundation',
    ]
);