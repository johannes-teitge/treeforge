<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 054
 * Media Edit Metadata
 *
 * Ziel:
 * - Bearbeiten-Link in Media Grid
 * - Detailseite /admin/media/edit.php?id=...
 * - Metadaten bearbeiten und speichern
 * - Titel, Alt, Caption, Beschreibung, Kategorie, Tags, Copyright, Photographer, License
 * - Fokuspunkt vorbereiten
 * - Dateiinfos anzeigen
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 054 Media Edit Metadata gestartet');

    $write($root . '/app/Modules/Media/MediaRepository.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

class MediaRepository
{
    protected MediaConfig $config;

    public function __construct(protected string $root)
    {
        $this->config = new MediaConfig($root);
    }

    public function all(): array
    {
        $files = glob($this->config->metaDir() . '/**/*.json', GLOB_BRACE) ?: [];
        $direct = glob($this->config->metaDir() . '/*.json') ?: [];
        $files = array_merge($direct, $files);

        $items = [];

        foreach ($files as $file) {
            $data = json_decode((string)file_get_contents($file), true);

            if (is_array($data)) {
                $data['_meta_file'] = $file;
                $items[] = $this->normalize($data);
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));

        return $items;
    }

    public function findById(string $id): ?array
    {
        foreach ($this->all() as $item) {
            if ((string)($item['id'] ?? '') === $id) {
                return $item;
            }
        }

        return null;
    }

    public function save(array $item): void
    {
        $relativePath = (string)($item['relative_path'] ?? '');

        if ($relativePath === '') {
            throw new \RuntimeException('relative_path fehlt.');
        }

        $file = $this->metaFileForRelativePath($relativePath);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        unset($item['_meta_file']);

        $item['updated_at'] = date('c');

        file_put_contents(
            $file,
            json_encode($this->normalize($item), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function publicUrl(array $item): string
    {
        return $this->config->publicOriginalUrl((string)($item['relative_path'] ?? ''));
    }

    protected function metaFileForRelativePath(string $relativePath): string
    {
        return rtrim($this->config->metaDir(), '/\\') . '/' . ltrim(str_replace('\\', '/', $relativePath), '/') . '.json';
    }

    protected function normalize(array $item): array
    {
        $item['title'] ??= pathinfo((string)($item['filename'] ?? ''), PATHINFO_FILENAME);
        $item['alt'] ??= '';
        $item['caption'] ??= '';
        $item['description'] ??= '';
        $item['category'] ??= '';
        $item['tags'] ??= [];
        $item['copyright'] ??= '';
        $item['photographer'] ??= '';
        $item['license'] ??= '';
        $item['focus_x'] ??= 50;
        $item['focus_y'] ??= 50;
        $item['featured'] ??= false;
        $item['usage_count'] ??= 0;
        $item['last_used'] ??= null;
        $item['versions'] ??= [];

        return $item;
    }
}
PHP);

    $mediaIndex = $root . '/public/admin/media/index.php';
    if (file_exists($mediaIndex)) {
        $content = file_get_contents($mediaIndex);

        // Add repository use if needed is not necessary if existing page uses manager.
        $content = str_replace(
            "Ansehen</a>'",
            "Ansehen</a>' . '<a class=\"tf-media-button secondary\" href=\"/admin/media/edit.php?id=' . e((string)(\$item['id'] ?? '')) . '\">Bearbeiten</a>'",
            $content
        );

        $content = str_replace(
            "URL kopieren</button>'",
            "URL kopieren</button>'",
            $content
        );

        $write($mediaIndex, $content);
    }

    $write($root . '/public/admin/media/edit.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Settings\SettingsManager;
use TreeForge\Modules\Media\MediaRepository;

$root = dirname(__DIR__, 3);
$repo = new MediaRepository($root);
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
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$url = $repo->publicUrl($item);
$kind = (string)($item['kind'] ?? '');
$isImage = in_array($kind, ['image', 'vector'], true);

$preview = $isImage
    ? '<img src="' . e($url) . '" alt="' . e((string)($item['alt'] ?? '')) . '">'
    : '<div class="tf-media-file-preview">' . e(strtoupper((string)($item['extension'] ?? 'FILE'))) . '</div>';

$tags = implode(', ', (array)($item['tags'] ?? []));

$content = '';

if ($saved) {
    $content .= '<div class="tf-notice success">Metadaten wurden gespeichert.</div>';
}

if ($error !== '') {
    $content .= '<div class="tf-notice error">' . e($error) . '</div>';
}

$content .= '<div class="tf-media-edit-top">'
    . '<a class="tf-admin-button secondary" href="/admin/media/">Zur Medienbibliothek</a>'
    . '<a class="tf-admin-button secondary" href="' . e($url) . '" target="_blank" rel="noopener">Original ansehen</a>'
    . '</div>';

$content .= '<form method="post" class="tf-media-edit-layout">'
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
    . '<dt>Größe</dt><dd>' . e(bytesHuman((int)($item['size'] ?? 0))) . '</dd>'
    . '<dt>Maße</dt><dd>' . e(($item['width'] ?? '-') . ' × ' . ($item['height'] ?? '-') . ' px') . '</dd>'
    . '<dt>Erstellt</dt><dd>' . e($item['created_at'] ?? $item['uploaded_at'] ?? '') . '</dd>'
    . '<dt>Geändert</dt><dd>' . e($item['updated_at'] ?? '') . '</dd>'
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
    . '<label><span>Kategorie</span><input type="text" name="category" value="' . e($item['category'] ?? '') . '"></label>'
    . '<label><span>Tags</span><input type="text" name="tags" value="' . e($tags) . '"><small>Kommagetrennt, z. B. hero, blog, produkt</small></label>'
    . '</div>'

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

echo (new AdminLayout())->render(
    'Medium bearbeiten',
    $content,
    'media',
    [
        'site_name' => (string)($data['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Medienbibliothek',
    ]
);
PHP);

    $css = $root . '/public/assets/css/media.css';
    if (file_exists($css)) {
        $mediaCss = file_get_contents($css);
        if (!str_contains($mediaCss, '.tf-media-edit-layout')) {
            $mediaCss .= <<<'CSS'

.tf-media-edit-top {
  display: flex;
  justify-content: flex-end;
  gap: .5rem;
  margin-bottom: 1rem;
}

.tf-media-edit-layout {
  display: grid;
  grid-template-columns: minmax(320px, 460px) minmax(0, 1fr);
  gap: 1rem;
  align-items: start;
}

.tf-media-edit-preview,
.tf-media-edit-form {
  min-width: 0;
}

.tf-media-edit-image {
  background:
    linear-gradient(45deg, var(--tf-media-checker-a, #FFFFFF) 25%, transparent 25%),
    linear-gradient(-45deg, var(--tf-media-checker-a, #FFFFFF) 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, var(--tf-media-checker-a, #FFFFFF) 75%),
    linear-gradient(-45deg, transparent 75%, var(--tf-media-checker-a, #FFFFFF) 75%),
    var(--tf-media-checker-b, #F0F2F4);
  background-size: 20px 20px;
  background-position: 0 0, 0 10px, 10px -10px, -10px 0;
  border: 1px solid var(--tf-border-default, #D7DDE2);
  border-radius: var(--tf-radius-md, .75rem);
  overflow: hidden;
  margin-bottom: 1rem;
  min-height: 240px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tf-media-edit-image img {
  max-width: 100%;
  max-height: 460px;
  object-fit: contain;
  display: block;
}

.tf-media-file-preview {
  font-size: 2rem;
  font-weight: 700;
  color: var(--tf-text-muted, #64727D);
}

.tf-media-info-list {
  display: grid;
  grid-template-columns: 135px minmax(0, 1fr);
  gap: .45rem .75rem;
  margin: 0 0 1rem;
}

.tf-media-info-list dt {
  color: var(--tf-text-muted, #64727D);
  font-weight: 560;
}

.tf-media-info-list dd {
  margin: 0;
  color: var(--tf-text-default, #071725);
  overflow-wrap: anywhere;
}

.tf-media-edit-form h3 {
  margin: 1.25rem 0 .5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--tf-border-soft, #E5E9EC);
  font-size: 1rem;
  font-weight: 620;
  color: var(--tf-text-heading, #071725);
}

@media (max-width: 980px) {
  .tf-media-edit-layout {
    grid-template-columns: 1fr;
  }
}
CSS;
            $write($css, $mediaCss);
        }
    }

    $write($root . '/docs/treeforge/44-media-edit-metadata.md', <<<'MD'
# Media Edit Metadata

Patch 054 ergänzt die Bearbeitung von Medien-Metadaten.

## Route

```text
/admin/media/edit.php?id=...
```

## Felder

```text
Titel
Alt-Text
Caption
Beschreibung
Kategorie
Tags
Copyright
Fotograf / Quelle
Lizenz
Fokuspunkt X/Y
Featured
```

## Dateiinfos

Angezeigt werden:

```text
ID
Dateiname
Originalname
Pfad
MIME
Art
Größe
Maße
Erstellt
Geändert
Verwendungen
```

## SVG

SVG wird als Vektor erkannt.

Hinweis:

```text
SVG wird nicht in Presets gerendert.
Social Image Verhalten folgt den Media Settings.
```

## Nächste Schritte

- Kategorien verwalten
- Replace / Versioning
- Usage Tracking
- Media Picker
MD);

    $log('Patch 054 Media Edit Metadata fertig');
};
