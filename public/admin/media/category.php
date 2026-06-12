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
$categories = new MediaCategoryRepository($root);
$media = new MediaRepository($root);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = (string)($_GET['id'] ?? $_POST['id'] ?? '');
$category = $id !== '' ? $categories->find($id) : null;
$error = '';
$saved = false;

if (!$category) {
    http_response_code(404);

    echo (new AdminLayout())->render(
        'Kategorie nicht gefunden',
        '<div class="tf-notice error">Kategorie wurde nicht gefunden.</div><p><a class="tf-admin-button secondary" href="/admin/media/">Zur Medienbibliothek</a></p>',
        'media',
        ['subtitle' => 'Medienbibliothek']
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_category'])) {
            $categories->delete($id, $media->all());
            header('Location: /admin/media/');
            exit;
        }

        $category = $categories->update($id, [
            'label' => (string)($_POST['label'] ?? ''),
            'description' => (string)($_POST['description'] ?? ''),
            'icon' => (string)($_POST['icon'] ?? 'folder'),
            'color' => (string)($_POST['color'] ?? ''),
        ]);

        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$counts = $categories->counts($media->all());
$count = (int)($counts[$id] ?? 0);

$content = '';

if ($saved) {
    $content .= '<div class="tf-notice success">Kategorie wurde gespeichert.</div>';
}

if ($error !== '') {
    $content .= '<div class="tf-notice error">' . e($error) . '</div>';
}

$content .= '<div class="tf-media-edit-top">'
    . '<a class="tf-admin-button secondary" href="/admin/media/">Zur Medienbibliothek</a>'
    . '</div>';

$content .= '<form method="post" class="tf-admin-card tf-category-edit">'
    . '<input type="hidden" name="id" value="' . e($id) . '">'
    . '<h2>Kategorie bearbeiten</h2>'
    . '<p>Diese Einstellungen dienen später auch für Icons, Farben und Baumstruktur.</p>'
    . '<label><span>Name</span><input type="text" name="label" value="' . e($category['label'] ?? '') . '"></label>'
    . '<label><span>Beschreibung</span><textarea name="description">' . e($category['description'] ?? '') . '</textarea></label>'
    . '<div class="tf-page-grid">'
    . '<label><span>Icon</span><input type="text" name="icon" value="' . e($category['icon'] ?? 'folder') . '"><small>z. B. folder, image, download, star</small></label>'
    . '<label><span>Farbe</span><input type="text" name="color" value="' . e($category['color'] ?? '') . '"><small>z. B. #E2A900 oder leer</small></label>'
    . '</div>'
    . '<dl class="tf-system-info">'
    . '<dt>ID</dt><dd><code>' . e($category['id'] ?? '') . '</code></dd>'
    . '<dt>Medien</dt><dd>' . e($count) . '</dd>'
    . '<dt>Erstellt</dt><dd>' . e($category['created_at'] ?? '') . '</dd>'
    . '<dt>Geändert</dt><dd>' . e($category['updated_at'] ?? '') . '</dd>'
    . '</dl>'
    . '<div class="tf-admin-actions">'
    . '<button type="submit" class="tf-admin-button">Kategorie speichern</button>'
    . '<a class="tf-admin-button secondary" href="/admin/media/?category=' . e($id) . '">Medien anzeigen</a>'
    . '</div>'
    . '</form>';

$content .= '<form method="post" class="tf-admin-card tf-category-danger" onsubmit="return confirm(\'Kategorie wirklich löschen?\');">'
    . '<input type="hidden" name="id" value="' . e($id) . '">'
    . '<h3>Kategorie löschen</h3>'
    . '<p>Eine Kategorie kann nur gelöscht werden, wenn ihr keine Medien mehr zugeordnet sind.</p>'
    . '<button type="submit" name="delete_category" value="1" class="tf-admin-button danger"' . ($count > 0 ? ' disabled' : '') . '>Kategorie löschen</button>'
    . ($count > 0 ? '<div class="tf-warning">Diese Kategorie enthält noch ' . e($count) . ' Medien und kann deshalb nicht gelöscht werden.</div>' : '')
    . '</form>';

echo (new AdminLayout())->render(
    'Kategorie bearbeiten',
    $content,
    'media',
    [
        'site_name' => (string)($data['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Medienbibliothek',
    ]
);