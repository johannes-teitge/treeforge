<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Settings\SettingsManager;
use TreeForge\Core\System\Version;

$root = dirname(__DIR__, 3);
$settings = new SettingsManager($root);
$versionInfo = new Version($root);

$saved = false;
$error = '';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function checked(bool $value): string
{
    return $value ? ' checked' : '';
}

function selected(string $current, string $value): string
{
    return $current === $value ? ' selected' : '';
}

function csvToArray(string $value): array
{
    return array_values(array_filter(array_map(
        static fn(string $item): string => strtolower(trim($item)),
        explode(',', $value)
    )));
}

function arrayToCsv(mixed $value): string
{
    return implode(', ', (array)$value);
}

function inputValue(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    return (string)$value;
}

function normalizeImagePresets(array $presets): array
{
    $defaults = SettingsManager::defaults();
    $normalized = array_replace_recursive($defaults['media']['image_presets'] ?? [], $presets);

    foreach ($normalized as $key => $preset) {
        $normalized[$key]['width'] = isset($preset['width']) && $preset['width'] !== '' ? (int)$preset['width'] : null;
        $normalized[$key]['height'] = isset($preset['height']) && $preset['height'] !== '' ? (int)$preset['height'] : null;
        $normalized[$key]['quality'] = isset($preset['quality']) ? (int)$preset['quality'] : 82;
        $normalized[$key]['locked'] = !empty($preset['locked']);
    }

    if (isset($normalized['social'])) {
        $normalized['social']['width'] = 1200;
        $normalized['social']['height'] = 630;
        $normalized['social']['mode'] = 'cover';
        $normalized['social']['format'] = 'jpg';
        $normalized['social']['locked'] = true;
    }

    return $normalized;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $enabledLanguages = array_values(array_filter(array_map(
            static fn(string $value): string => trim($value),
            explode(',', (string)($_POST['languages']['enabled_languages'] ?? 'de'))
        )));

        if ($enabledLanguages === []) {
            $enabledLanguages = ['de'];
        }

        $defaultLanguage = trim((string)($_POST['languages']['default_language'] ?? $enabledLanguages[0]));

        if ($defaultLanguage === '') {
            $defaultLanguage = $enabledLanguages[0];
        }

        if (!in_array($defaultLanguage, $enabledLanguages, true)) {
            array_unshift($enabledLanguages, $defaultLanguage);
            $enabledLanguages = array_values(array_unique($enabledLanguages));
        }

        $settings->merge([
            'general' => [
                'site_name' => trim((string)($_POST['general']['site_name'] ?? 'TreeForge CMS')),
                'site_url' => trim((string)($_POST['general']['site_url'] ?? 'http://localhost')),
                'admin_email' => trim((string)($_POST['general']['admin_email'] ?? '')),
                'timezone' => trim((string)($_POST['general']['timezone'] ?? 'Europe/Berlin')),
            ],

            'languages' => [
                'default_language' => $defaultLanguage,
                'enabled_languages' => $enabledLanguages,
                'multilanguage' => isset($_POST['languages']['multilanguage']),
            ],

            'storage' => [
                'driver' => (string)($_POST['storage']['driver'] ?? 'file'),
                'database_path' => trim((string)($_POST['storage']['database_path'] ?? 'storage/database/treeforge.sqlite')),
            ],

            'media' => [
                'max_file_size_mb' => max(1, (int)($_POST['media']['max_file_size_mb'] ?? 10)),
                'max_files_per_upload' => max(1, (int)($_POST['media']['max_files_per_upload'] ?? 20)),
                'normalize_filenames' => isset($_POST['media']['normalize_filenames']),
                'unique_filenames' => isset($_POST['media']['unique_filenames']),
                'drag_drop_enabled' => isset($_POST['media']['drag_drop_enabled']),
                'chunk_upload_enabled' => isset($_POST['media']['chunk_upload_enabled']),
                'chunk_size_mb' => max(1, (int)($_POST['media']['chunk_size_mb'] ?? 5)),

                'file_types' => [
                    'images' => csvToArray((string)($_POST['media']['file_types']['images'] ?? 'jpg,jpeg,png,webp,gif,svg')),
                    'documents' => csvToArray((string)($_POST['media']['file_types']['documents'] ?? 'pdf,docx,xlsx,txt,csv,odt')),
                    'downloads' => csvToArray((string)($_POST['media']['file_types']['downloads'] ?? 'zip')),
                    'audio' => csvToArray((string)($_POST['media']['file_types']['audio'] ?? '')),
                    'video' => csvToArray((string)($_POST['media']['file_types']['video'] ?? '')),
                ],

                'svg' => [
                    'allow_upload' => isset($_POST['media']['svg']['allow_upload']),
                    'sanitize' => isset($_POST['media']['svg']['sanitize']),
                    'allow_as_image' => isset($_POST['media']['svg']['allow_as_image']),
                    'allow_as_logo' => isset($_POST['media']['svg']['allow_as_logo']),
                    'allow_as_icon' => isset($_POST['media']['svg']['allow_as_icon']),
                    'allow_as_social_image' => isset($_POST['media']['svg']['allow_as_social_image']),
                    'show_social_warning' => isset($_POST['media']['svg']['show_social_warning']),
                ],

                'zip' => [
                    'allow_upload' => isset($_POST['media']['zip']['allow_upload']),
                    'allow_as_download' => isset($_POST['media']['zip']['allow_as_download']),
                    'allow_site_package' => isset($_POST['media']['zip']['allow_site_package']),
                    'allow_extract' => isset($_POST['media']['zip']['allow_extract']),
                    'extract_admin_only' => isset($_POST['media']['zip']['extract_admin_only']),
                    'max_size_mb' => max(1, (int)($_POST['media']['zip']['max_size_mb'] ?? 50)),
                ],

                'downloads' => [
                    'enabled' => isset($_POST['media']['downloads']['enabled']),
                    'max_size_mb' => max(1, (int)($_POST['media']['downloads']['max_size_mb'] ?? 50)),
                    'force_download_default' => isset($_POST['media']['downloads']['force_download_default']),
                ],

                'accessibility' => [
                    'require_alt_for_images' => isset($_POST['media']['accessibility']['require_alt_for_images']),
                    'warn_missing_alt' => isset($_POST['media']['accessibility']['warn_missing_alt']),
                    'require_title' => isset($_POST['media']['accessibility']['require_title']),
                ],

                'replace' => [
                    'enabled' => isset($_POST['media']['replace']['enabled']),
                    'keep_media_id' => isset($_POST['media']['replace']['keep_media_id']),
                    'keep_old_versions' => isset($_POST['media']['replace']['keep_old_versions']),
                    'max_versions' => max(1, (int)($_POST['media']['replace']['max_versions'] ?? 10)),
                    'invalidate_cache_on_replace' => isset($_POST['media']['replace']['invalidate_cache_on_replace']),
                ],

                'security' => [
                    'scan_upload_names' => isset($_POST['media']['security']['scan_upload_names']),
                    'block_php_files' => isset($_POST['media']['security']['block_php_files']),
                    'block_double_extensions' => isset($_POST['media']['security']['block_double_extensions']),
                    'block_executable_mime' => isset($_POST['media']['security']['block_executable_mime']),
                    'strip_exif' => isset($_POST['media']['security']['strip_exif']),
                ],

                'image_presets' => normalizeImagePresets((array)($_POST['media']['image_presets'] ?? [])),

                'render_cache' => [
                    'enabled' => isset($_POST['media']['render_cache']['enabled']),
                    'keep_originals' => true,
                    'cache_dir' => trim((string)($_POST['media']['render_cache']['cache_dir'] ?? 'storage/media/cache')),
                    'auto_generate_on_upload' => isset($_POST['media']['render_cache']['auto_generate_on_upload']),
                    'generate_on_demand' => isset($_POST['media']['render_cache']['generate_on_demand']),
                    'clear_unused_after_days' => max(0, (int)($_POST['media']['render_cache']['clear_unused_after_days'] ?? 90)),
                ],
            ],
        ]);

        $settings->save();
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$data = $settings->all();
$media = (array)($data['media'] ?? []);
$fileTypes = (array)($media['file_types'] ?? []);
$svg = (array)($media['svg'] ?? []);
$zip = (array)($media['zip'] ?? []);
$downloads = (array)($media['downloads'] ?? []);
$accessibility = (array)($media['accessibility'] ?? []);
$replace = (array)($media['replace'] ?? []);
$mediaSecurity = (array)($media['security'] ?? []);
$imagePresets = (array)($media['image_presets'] ?? []);
$renderCache = (array)($media['render_cache'] ?? []);

$content = '';

if ($saved) {
    $content .= '<div class="tf-notice success">Einstellungen wurden gespeichert.</div>';
}

if ($error !== '') {
    $content .= '<div class="tf-notice error">' . e($error) . '</div>';
}

$content .= '<form method="post" class="tf-settings-form">'
    . '<aside class="tf-settings-tabs" aria-label="Settings Navigation">'
    . '<a href="#general">General</a>'
    . '<a href="#languages">Languages</a>'
    . '<a href="#storage">Storage</a>'
    . '<a href="#media-settings">Media Settings</a>'
    . '<a href="#image-presets">Image Presets</a>'
    . '<a href="#system">System Info</a>'
    . '</aside>'
    . '<section class="tf-settings-content">';

$content .= '<section id="general" class="tf-settings-card">'
    . '<h1>General</h1>'
    . '<p>Grunddaten der Website und Umgebung.</p>'
    . '<label><span>Site Name</span><input type="text" name="general[site_name]" value="' . e($data['general']['site_name'] ?? '') . '"></label>'
    . '<label><span>Site URL</span><input type="url" name="general[site_url]" value="' . e($data['general']['site_url'] ?? '') . '"></label>'
    . '<label><span>Admin E-Mail</span><input type="email" name="general[admin_email]" value="' . e($data['general']['admin_email'] ?? '') . '"></label>'
    . '<label><span>Timezone</span><input type="text" name="general[timezone]" value="' . e($data['general']['timezone'] ?? 'Europe/Berlin') . '"></label>'
    . '</section>';

$content .= '<section id="languages" class="tf-settings-card">'
    . '<h2>Languages</h2>'
    . '<p>Auch ohne Multilanguage wird intern immer eine Default-Sprache gesetzt.</p>'
    . '<label><span>Default Language</span><input type="text" name="languages[default_language]" value="' . e($data['languages']['default_language'] ?? 'de') . '"></label>'
    . '<label><span>Enabled Languages</span><input type="text" name="languages[enabled_languages]" value="' . e(implode(',', (array)($data['languages']['enabled_languages'] ?? ['de']))) . '"><small>Kommagetrennt, z. B. de,en,fr</small></label>'
    . '<label class="tf-check"><input type="checkbox" name="languages[multilanguage]" value="1"' . checked((bool)($data['languages']['multilanguage'] ?? false)) . '><span>Multilanguage aktivieren</span></label>'
    . '</section>';

$content .= '<section id="storage" class="tf-settings-card">'
    . '<h2>Storage</h2>'
    . '<p>Aktuell arbeitet TreeForge mit FileStorage. SQLite/MySQL sind vorbereitet.</p>'
    . '<label><span>Storage Driver</span><select name="storage[driver]">'
    . '<option value="file"' . selected((string)($data['storage']['driver'] ?? 'file'), 'file') . '>File</option>'
    . '<option value="sqlite"' . selected((string)($data['storage']['driver'] ?? 'file'), 'sqlite') . '>SQLite</option>'
    . '<option value="mysql"' . selected((string)($data['storage']['driver'] ?? 'file'), 'mysql') . '>MySQL</option>'
    . '</select></label>'
    . '<label><span>SQLite Database Path</span><input type="text" name="storage[database_path]" value="' . e($data['storage']['database_path'] ?? 'storage/database/treeforge.sqlite') . '"></label>'
    . '<div class="tf-warning">Der Storage-Treiber wird aktuell nur gespeichert. Eine aktive Umschaltung erfolgt erst mit dem späteren StorageInterface-Patch.</div>'
    . '</section>';

$content .= '<section id="media-settings" class="tf-settings-card">'
    . '<h2>Media Settings</h2>'
    . '<p>Zentrale Regeln für Uploads, Dateitypen, SVG, ZIP/Downloads, Replace/Versioning und Upload-Sicherheit.</p>'

    . '<h3>Uploads</h3>'
    . '<div class="tf-settings-grid">'
    . '<label><span>Max Upload Size MB</span><input type="number" min="1" name="media[max_file_size_mb]" value="' . e($media['max_file_size_mb'] ?? 10) . '"></label>'
    . '<label><span>Max Files per Upload</span><input type="number" min="1" name="media[max_files_per_upload]" value="' . e($media['max_files_per_upload'] ?? 20) . '"></label>'
    . '<label><span>Chunk Size MB</span><input type="number" min="1" name="media[chunk_size_mb]" value="' . e($media['chunk_size_mb'] ?? 5) . '"></label>'
    . '</div>'
    . '<label class="tf-check"><input type="checkbox" name="media[drag_drop_enabled]" value="1"' . checked((bool)($media['drag_drop_enabled'] ?? true)) . '><span>Drag & Drop Upload aktiv</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[normalize_filenames]" value="1"' . checked((bool)($media['normalize_filenames'] ?? true)) . '><span>Dateinamen normalisieren</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[unique_filenames]" value="1"' . checked((bool)($media['unique_filenames'] ?? true)) . '><span>Automatisch eindeutige Dateinamen erzeugen</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[chunk_upload_enabled]" value="1"' . checked((bool)($media['chunk_upload_enabled'] ?? false)) . '><span>Chunk Upload vorbereiten</span></label>'

    . '<h3>Dateitypen</h3>'
    . '<div class="tf-settings-grid">'
    . '<label><span>Bilder</span><input type="text" name="media[file_types][images]" value="' . e(arrayToCsv($fileTypes['images'] ?? [])) . '"><small>z. B. jpg,jpeg,png,webp,gif,svg</small></label>'
    . '<label><span>Dokumente</span><input type="text" name="media[file_types][documents]" value="' . e(arrayToCsv($fileTypes['documents'] ?? [])) . '"><small>z. B. pdf,docx,xlsx,txt,csv,odt</small></label>'
    . '<label><span>Downloads/Archive</span><input type="text" name="media[file_types][downloads]" value="' . e(arrayToCsv($fileTypes['downloads'] ?? [])) . '"><small>z. B. zip,rar,7z</small></label>'
    . '<label><span>Audio</span><input type="text" name="media[file_types][audio]" value="' . e(arrayToCsv($fileTypes['audio'] ?? [])) . '"></label>'
    . '<label><span>Video</span><input type="text" name="media[file_types][video]" value="' . e(arrayToCsv($fileTypes['video'] ?? [])) . '"></label>'
    . '</div>'

    . '<h3>SVG</h3>'
    . '<label class="tf-check"><input type="checkbox" name="media[svg][allow_upload]" value="1"' . checked((bool)($svg['allow_upload'] ?? true)) . '><span>SVG Upload erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[svg][sanitize]" value="1"' . checked((bool)($svg['sanitize'] ?? true)) . '><span>SVG sanitizen</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[svg][allow_as_image]" value="1"' . checked((bool)($svg['allow_as_image'] ?? true)) . '><span>SVG in ImageNodes erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[svg][allow_as_logo]" value="1"' . checked((bool)($svg['allow_as_logo'] ?? true)) . '><span>SVG als Logo erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[svg][allow_as_icon]" value="1"' . checked((bool)($svg['allow_as_icon'] ?? true)) . '><span>SVG als Icon erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[svg][allow_as_social_image]" value="1"' . checked((bool)($svg['allow_as_social_image'] ?? false)) . '><span>SVG für Social Images erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[svg][show_social_warning]" value="1"' . checked((bool)($svg['show_social_warning'] ?? true)) . '><span>Warnung bei SVG Social Images anzeigen</span></label>'
    . '<div class="tf-warning">Empfehlung: Social Images als 1200×630 JPG. SVG für Social Media ist standardmäßig gesperrt, kann aber bewusst erlaubt werden.</div>'

    . '<h3>ZIP / Downloads</h3>'
    . '<div class="tf-settings-grid">'
    . '<label><span>ZIP Max Size MB</span><input type="number" min="1" name="media[zip][max_size_mb]" value="' . e($zip['max_size_mb'] ?? 50) . '"></label>'
    . '<label><span>Download Max Size MB</span><input type="number" min="1" name="media[downloads][max_size_mb]" value="' . e($downloads['max_size_mb'] ?? 50) . '"></label>'
    . '</div>'
    . '<label class="tf-check"><input type="checkbox" name="media[zip][allow_upload]" value="1"' . checked((bool)($zip['allow_upload'] ?? true)) . '><span>ZIP Upload erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[zip][allow_as_download]" value="1"' . checked((bool)($zip['allow_as_download'] ?? true)) . '><span>ZIP als Download erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[zip][allow_site_package]" value="1"' . checked((bool)($zip['allow_site_package'] ?? true)) . '><span>ZIP als Site Package erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[zip][allow_extract]" value="1"' . checked((bool)($zip['allow_extract'] ?? false)) . '><span>ZIP entpacken erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[zip][extract_admin_only]" value="1"' . checked((bool)($zip['extract_admin_only'] ?? true)) . '><span>Entpacken nur für Admin</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[downloads][enabled]" value="1"' . checked((bool)($downloads['enabled'] ?? true)) . '><span>Downloads aktivieren</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[downloads][force_download_default]" value="1"' . checked((bool)($downloads['force_download_default'] ?? false)) . '><span>Downloads standardmäßig erzwingen</span></label>'

    . '<h3>Replace / Versioning</h3>'
    . '<div class="tf-settings-grid">'
    . '<label><span>Max Versionen</span><input type="number" min="1" name="media[replace][max_versions]" value="' . e($replace['max_versions'] ?? 10) . '"></label>'
    . '</div>'
    . '<label class="tf-check"><input type="checkbox" name="media[replace][enabled]" value="1"' . checked((bool)($replace['enabled'] ?? true)) . '><span>Dateien ersetzen erlauben</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[replace][keep_media_id]" value="1"' . checked((bool)($replace['keep_media_id'] ?? true)) . '><span>Media-ID beim Ersetzen behalten</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[replace][keep_old_versions]" value="1"' . checked((bool)($replace['keep_old_versions'] ?? true)) . '><span>Alte Versionen behalten</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[replace][invalidate_cache_on_replace]" value="1"' . checked((bool)($replace['invalidate_cache_on_replace'] ?? true)) . '><span>Cache beim Ersetzen ungültig machen</span></label>'

    . '<h3>Barrierefreiheit / SEO</h3>'
    . '<label class="tf-check"><input type="checkbox" name="media[accessibility][require_alt_for_images]" value="1"' . checked((bool)($accessibility['require_alt_for_images'] ?? false)) . '><span>Alt-Text für Bilder verpflichtend</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[accessibility][warn_missing_alt]" value="1"' . checked((bool)($accessibility['warn_missing_alt'] ?? true)) . '><span>Warnung bei fehlendem Alt-Text</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[accessibility][require_title]" value="1"' . checked((bool)($accessibility['require_title'] ?? false)) . '><span>Titel verpflichtend</span></label>'

    . '<h3>Upload Security</h3>'
    . '<label class="tf-check"><input type="checkbox" name="media[security][scan_upload_names]" value="1"' . checked((bool)($mediaSecurity['scan_upload_names'] ?? true)) . '><span>Dateinamen prüfen</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[security][block_php_files]" value="1"' . checked((bool)($mediaSecurity['block_php_files'] ?? true)) . '><span>PHP-Dateien blockieren</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[security][block_double_extensions]" value="1"' . checked((bool)($mediaSecurity['block_double_extensions'] ?? true)) . '><span>Doppelte Endungen blockieren</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[security][block_executable_mime]" value="1"' . checked((bool)($mediaSecurity['block_executable_mime'] ?? true)) . '><span>Ausführbare MIME-Typen blockieren</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[security][strip_exif]" value="1"' . checked((bool)($mediaSecurity['strip_exif'] ?? true)) . '><span>EXIF-Daten entfernen</span></label>'
    . '</section>';

$content .= '<section id="image-presets" class="tf-settings-card">'
    . '<h2>Image Presets</h2>'
    . '<p>Globale Bildgrößen für ImageNodes, Page Settings, Social Images, Lightbox und spätere Render-Caches.</p>';

foreach ($imagePresets as $presetKey => $preset) {
    $locked = !empty($preset['locked']);

    $content .= '<div class="tf-image-preset">'
        . '<div class="tf-image-preset-head">'
        . '<strong>' . e($preset['label'] ?? $presetKey) . '</strong>'
        . '<code>' . e($presetKey) . '</code>'
        . ($locked ? '<span>fest</span>' : '')
        . '</div>'
        . '<div class="tf-image-preset-grid">'
        . '<label><span>Label</span><input type="text" name="media[image_presets][' . e($presetKey) . '][label]" value="' . e($preset['label'] ?? '') . '"' . ($locked && $presetKey === 'social' ? ' readonly' : '') . '></label>'
        . '<label><span>Breite</span><input type="number" min="1" name="media[image_presets][' . e($presetKey) . '][width]" value="' . e(inputValue($preset['width'] ?? '')) . '"' . ($locked && $presetKey === 'social' ? ' readonly' : '') . '></label>'
        . '<label><span>Höhe</span><input type="number" min="1" name="media[image_presets][' . e($presetKey) . '][height]" value="' . e(inputValue($preset['height'] ?? '')) . '"' . ($locked && $presetKey === 'social' ? ' readonly' : '') . '><small>Leer = Auto</small></label>'
        . '<label><span>Modus</span><select name="media[image_presets][' . e($presetKey) . '][mode]"' . ($locked && $presetKey === 'social' ? ' disabled' : '') . '>'
        . '<option value="contain"' . selected((string)($preset['mode'] ?? 'contain'), 'contain') . '>Contain</option>'
        . '<option value="cover"' . selected((string)($preset['mode'] ?? 'contain'), 'cover') . '>Cover</option>'
        . '</select></label>'
        . ($locked && $presetKey === 'social' ? '<input type="hidden" name="media[image_presets][' . e($presetKey) . '][mode]" value="cover">' : '')
        . '<label><span>Format</span><select name="media[image_presets][' . e($presetKey) . '][format]"' . ($locked && $presetKey === 'social' ? ' disabled' : '') . '>'
        . '<option value="webp"' . selected((string)($preset['format'] ?? 'webp'), 'webp') . '>WebP</option>'
        . '<option value="jpg"' . selected((string)($preset['format'] ?? 'webp'), 'jpg') . '>JPG</option>'
        . '<option value="png"' . selected((string)($preset['format'] ?? 'webp'), 'png') . '>PNG</option>'
        . '</select></label>'
        . ($locked && $presetKey === 'social' ? '<input type="hidden" name="media[image_presets][' . e($presetKey) . '][format]" value="jpg">' : '')
        . '<label><span>Qualität</span><input type="number" min="1" max="100" name="media[image_presets][' . e($presetKey) . '][quality]" value="' . e($preset['quality'] ?? 82) . '"></label>'
        . '<input type="hidden" name="media[image_presets][' . e($presetKey) . '][locked]" value="' . ($locked ? '1' : '0') . '">'
        . '</div>';

    if ($presetKey === 'content-large') {
        $content .= '<p class="tf-image-preset-note">Empfohlen für Zoom/Lightbox.</p>';
    }

    if ($presetKey === 'social') {
        $content .= '<p class="tf-image-preset-note">Social bleibt absichtlich 1200×630 JPG Cover für Facebook, LinkedIn, WhatsApp und OpenGraph.</p>';
    }

    $content .= '</div>';
}

$content .= '<h3>Render Cache</h3>'
    . '<label class="tf-check"><input type="checkbox" name="media[render_cache][enabled]" value="1"' . checked((bool)($renderCache['enabled'] ?? true)) . '><span>Render Cache aktiv</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[render_cache][generate_on_demand]" value="1"' . checked((bool)($renderCache['generate_on_demand'] ?? true)) . '><span>Derivate bei Bedarf erzeugen</span></label>'
    . '<label class="tf-check"><input type="checkbox" name="media[render_cache][auto_generate_on_upload]" value="1"' . checked((bool)($renderCache['auto_generate_on_upload'] ?? false)) . '><span>Derivate schon beim Upload erzeugen</span></label>'
    . '<label><span>Cache-Verzeichnis</span><input type="text" name="media[render_cache][cache_dir]" value="' . e($renderCache['cache_dir'] ?? 'storage/media/cache') . '"></label>'
    . '<label><span>Ungenutzte Cache-Dateien löschen nach Tagen</span><input type="number" min="0" name="media[render_cache][clear_unused_after_days]" value="' . e($renderCache['clear_unused_after_days'] ?? 90) . '"></label>'
    . '<div class="tf-warning">Originale bleiben erhalten. Derivate werden später bei Bedarf erzeugt und können jederzeit neu aufgebaut werden.</div>'
    . '</section>';

$content .= '<section id="system" class="tf-settings-card">'
    . '<h2>System Info</h2>'
    . '<p>Erste technische Übersicht für spätere Support- und Diagnosefunktionen.</p>'
    . '<dl class="tf-system-info">'
    . '<dt>TreeForge Version</dt><dd>' . e($versionInfo->version()) . '</dd>'
    . '<dt>Build</dt><dd>' . e($versionInfo->build()) . '</dd>'
    . '<dt>Update Channel</dt><dd>' . e($versionInfo->channel()) . '</dd>'
    . '<dt>PHP Version</dt><dd>' . e(PHP_VERSION) . '</dd>'
    . '<dt>Storage Driver</dt><dd>' . e($data['storage']['driver'] ?? 'file') . '</dd>'
    . '<dt>Default Language</dt><dd>' . e($data['languages']['default_language'] ?? 'de') . '</dd>'
    . '<dt>Git Tag</dt><dd>' . e($versionInfo->gitTag()) . '</dd>'
    . '<dt>Git Commit</dt><dd>' . e($versionInfo->gitCommit()) . '</dd>'
    . '<dt>Settings File</dt><dd><code>storage/system/settings.json</code></dd>'
    . '</dl>'
    . '</section>';

$content .= '<div class="tf-settings-savebar"><button type="submit">Einstellungen speichern</button></div>'
    . '</section>'
    . '</form>';

echo (new AdminLayout())->render(
    'Settings',
    $content,
    'settings',
    [
        'site_name' => (string)($data['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Zentrale Systemeinstellungen',
    ]
);