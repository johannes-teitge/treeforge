<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Settings\SettingsManager;
use TreeForge\Modules\Media\MediaCategoryRepository;
use TreeForge\Modules\Media\MediaRepository;
use TreeForge\Modules\Media\MediaReplaceService;

$root = dirname(__DIR__, 3);
$repo = new MediaRepository($root);
$categories = new MediaCategoryRepository($root);
$replaceService = new MediaReplaceService($root);
$settings = new SettingsManager($root);
$data = $settings->all();

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function checked(bool $value): string
{
    return $value ? ' checked' : '';
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

function csvToTags(string $value): array
{
    return array_values(array_filter(array_map(
        static fn(string $tag): string => trim($tag),
        explode(',', $value)
    )));
}

function categoryOptions(MediaCategoryRepository $categories, string $current): string
{
    $html = '<option value="">Nicht einsortiert</option>';

    foreach ($categories->all() as $category) {
        $id = (string)($category['id'] ?? '');
        $label = (string)($category['label'] ?? $id);
        $selected = $id === $current ? ' selected' : '';

        $html .= '<option value="' . e($id) . '"' . $selected . '>' . e($label) . '</option>';
    }

    return $html;
}

$id = (string)($_GET['id'] ?? $_POST['id'] ?? '');
$item = $id !== '' ? $repo->findById($id) : null;
$saved = false;
$error = '';

if (!$item) {
    http_response_code(404);

    echo (new AdminLayout())->render(
        'Medium nicht gefunden',
        '<div class="tf-notice error">Medium wurde nicht gefunden.</div><p><a class="tf-admin-button secondary" href="/admin/media/">Zur Medienbibliothek</a></p>',
        'media',
        ['subtitle' => 'Medienbibliothek']
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['replace_file'])) {
            if (empty($_FILES['replacement']) || ($_FILES['replacement']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Bitte eine Ersatzdatei auswählen.');
            }

            $item = $replaceService->replace($id, $_FILES['replacement']);
            $saved = true;
        } else {
            $item['title'] = trim((string)($_POST['title'] ?? ''));
            $item['alt'] = trim((string)($_POST['alt'] ?? ''));
            $item['caption'] = trim((string)($_POST['caption'] ?? ''));
            $item['description'] = trim((string)($_POST['description'] ?? ''));
            $item['category'] = trim((string)($_POST['category'] ?? ''));
            $item['tags'] = csvToTags((string)($_POST['tags'] ?? ''));
            $item['copyright'] = trim((string)($_POST['copyright'] ?? ''));
            $item['photographer'] = trim((string)($_POST['photographer'] ?? ''));
            $item['license'] = trim((string)($_POST['license'] ?? ''));
            $item['focus_x'] = max(0, min(100, (int)($_POST['focus_x'] ?? 50)));
            $item['focus_y'] = max(0, min(100, (int)($_POST['focus_y'] ?? 50)));
            $item['featured'] = isset($_POST['featured']);

            $repo->save($item);
            $item = $repo->findById($id) ?? $item;
            $saved = true;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$url = $repo->publicUrl($item);
$previewUrl = method_exists($repo, 'publicUrlWithVersion') ? $repo->publicUrlWithVersion($item) : $url;

$kind = (string)($item['kind'] ?? '');
$isImage = in_array($kind, ['image', 'vector'], true);

$preview = $isImage
    ? '<img src="' . e($previewUrl) . '" alt="' . e((string)($item['alt'] ?? '')) . '">'
    : '<div class="tf-media-file-preview">' . e(strtoupper((string)($item['extension'] ?? 'FILE'))) . '</div>';

$tags = implode(', ', (array)($item['tags'] ?? []));
$versions = (array)($item['versions'] ?? []);
$currentCategory = (string)($item['category'] ?? '');
$categoryOptions = categoryOptions($categories, $currentCategory);

$content = '';

if ($saved) {
    $content .= '<div class="tf-notice success">Änderungen wurden gespeichert.</div>';
}

if ($error !== '') {
    $content .= '<div class="tf-notice error">' . e($error) . '</div>';
}

$content .= '<div class="tf-media-edit-top">'
    . '<a class="tf-admin-button secondary" href="/admin/media/">Zur Medienbibliothek</a>'
    . '<a class="tf-admin-button secondary" href="' . e($url) . '" target="_blank" rel="noopener">Original ansehen</a>'
    . '</div>';

$content .= '<form method="post" enctype="multipart/form-data" class="tf-media-edit-layout">'
    . '<input type="hidden" name="id" value="' . e($id) . '">'

    . '<section class="tf-media-edit-preview tf-admin-card">'
    . '<div class="tf-media-edit-image">' . $preview . '</div>'
    . '<dl class="tf-media-info-list">'
    . '<dt>ID</dt><dd><code>' . e($item['id'] ?? '') . '</code></dd>'
    . '<dt>Dateiname</dt><dd>' . e($item['filename'] ?? '') . '</dd>'
    . '<dt>Originalname</dt><dd>' . e($item['original_name'] ?? '') . '</dd>'
    . '<dt>Pfad</dt><dd><code>' . e($item['relative_path'] ?? '') . '</code></dd>'
    . '<dt>Typ</dt><dd>' . e($item['mime'] ?? '') . '</dd>'
    . '<dt>Art</dt><dd>' . e($item['kind'] ?? '') . '</dd>'
    . '<dt>Kategorie</dt><dd>' . e($currentCategory !== '' ? $categories->labelFor($currentCategory) : 'Nicht einsortiert') . '</dd>'
    . '<dt>Größe</dt><dd>' . e(bytesHuman((int)($item['size'] ?? 0))) . '</dd>'
    . '<dt>Maße</dt><dd>' . e(($item['width'] ?? '-') . ' × ' . ($item['height'] ?? '-') . ' px') . '</dd>'
    . '<dt>Erstellt</dt><dd>' . e($item['created_at'] ?? $item['uploaded_at'] ?? '') . '</dd>'
    . '<dt>Geändert</dt><dd>' . e($item['updated_at'] ?? '') . '</dd>'
    . '<dt>Ersetzt</dt><dd>' . e($item['replaced_at'] ?? '-') . '</dd>'
    . '<dt>Versionen</dt><dd>' . e(count($versions)) . '</dd>'
    . '<dt>Verwendungen</dt><dd>' . e($item['usage_count'] ?? 0) . '</dd>'
    . '</dl>';

if ($kind === 'vector') {
    $svgSettings = (array)($data['media']['svg'] ?? []);
    $socialAllowed = !empty($svgSettings['allow_as_social_image']);
    $content .= '<div class="tf-warning">SVG ist ein Vektorbild. Es wird nicht in Presets gerendert. Social Images: ' . e($socialAllowed ? 'erlaubt' : 'standardmäßig gesperrt') . '.</div>';
}

$content .= '</section>'

    . '<section class="tf-media-edit-form tf-admin-card">'
    . '<h2>Metadaten</h2>'
    . '<p>Diese Informationen werden später von ImageNodes, SEO, Social Images, Suche und Barrierefreiheit verwendet.</p>'

    . '<label><span>Titel</span><input type="text" name="title" value="' . e($item['title'] ?? '') . '"></label>'
    . '<label><span>Alt-Text</span><input type="text" name="alt" value="' . e($item['alt'] ?? '') . '"><small>Beschreibung für Screenreader und Barrierefreiheit.</small></label>'
    . '<label><span>Bildunterschrift</span><textarea name="caption">' . e($item['caption'] ?? '') . '</textarea></label>'
    . '<label><span>Beschreibung</span><textarea name="description">' . e($item['description'] ?? '') . '</textarea></label>'

    . '<div class="tf-page-grid">'
    . '<label><span>Kategorie</span><select name="category">' . $categoryOptions . '</select></label>'
    . '<label><span>Tags</span><input type="text" name="tags" value="' . e($tags) . '"><small>Kommagetrennt, z. B. hero, blog, produkt</small></label>'
    . '</div>'

    . '<h3>Datei ersetzen / Versionierung</h3>'
    . '<p>Die Media-ID und der Ziel-Dateiname bleiben erhalten. Die bisherige Datei wird als Version gesichert. Formatwechsel ist aktuell nicht erlaubt.</p>'
    . '<label><span>Neue Datei</span><input type="file" name="replacement"><small>Erlaubt ist aktuell nur derselbe Dateityp wie die Originaldatei: .' . e($item['extension'] ?? '') . '</small></label>'
    . '<button type="submit" name="replace_file" value="1" class="tf-admin-button secondary">Datei ersetzen</button>'

    . '<h3>Rechte / Quelle</h3>'
    . '<div class="tf-page-grid">'
    . '<label><span>Copyright</span><input type="text" name="copyright" value="' . e($item['copyright'] ?? '') . '"></label>'
    . '<label><span>Fotograf / Quelle</span><input type="text" name="photographer" value="' . e($item['photographer'] ?? '') . '"></label>'
    . '</div>'
    . '<label><span>Lizenz</span><input type="text" name="license" value="' . e($item['license'] ?? '') . '"></label>'

    . '<h3>Fokuspunkt</h3>'
    . '<p>Vorbereitung für Hero-Bilder und Cover-Crops. 50/50 entspricht der Bildmitte.</p>'
    . '<div class="tf-page-grid">'
    . '<label><span>Focus X (%)</span><input type="number" min="0" max="100" name="focus_x" value="' . e($item['focus_x'] ?? 50) . '"></label>'
    . '<label><span>Focus Y (%)</span><input type="number" min="0" max="100" name="focus_y" value="' . e($item['focus_y'] ?? 50) . '"></label>'
    . '</div>'

    . '<label class="tf-check"><input type="checkbox" name="featured" value="1"' . checked((bool)($item['featured'] ?? false)) . '><span>Featured markieren</span></label>'

    . '<div class="tf-admin-actions">'
    . '<button type="submit" class="tf-admin-button">Metadaten speichern</button>'
    . '<a class="tf-admin-button secondary" href="/admin/media/">Abbrechen</a>'
    . '</div>'
    . '</section>'
    . '</form>';

if ($versions !== []) {
    $content .= '<div class="tf-media-versions tf-admin-card">'
        . '<h3>Versionen</h3>'
        . '<table class="tf-dashboard-table">'
        . '<thead><tr><th>Zeitpunkt</th><th>Datei</th><th>Ersetzt durch</th><th>Größe</th></tr></thead>'
        . '<tbody>';

    foreach (array_reverse($versions) as $version) {
        $content .= '<tr>'
            . '<td>' . e($version['created_at'] ?? '') . '</td>'
            . '<td><code>' . e($version['file'] ?? '') . '</code></td>'
            . '<td>' . e($version['replaced_by'] ?? '') . '</td>'
            . '<td>' . e(bytesHuman((int)($version['size'] ?? 0))) . '</td>'
            . '</tr>';
    }

    $content .= '</tbody></table></div>';
}

echo (new AdminLayout())->render(
    'Medium bearbeiten',
    $content,
    'media',
    [
        'site_name' => (string)($data['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Medienbibliothek',
    ]
);