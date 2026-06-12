<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 066
 * Media Category Management
 *
 * Ziel:
 * - Kategorien bearbeiten
 * - Kategorien löschen
 * - Beschreibung, Icon, Farbe vorbereiten
 * - Löschen blockieren, wenn Medien zugeordnet sind
 * - Media Sidebar mit Bearbeiten/Löschen Aktionen
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

    $log('Patch 066 Media Category Management gestartet');

    $write($root . '/app/Modules/Media/MediaCategoryRepository.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

class MediaCategoryRepository
{
    protected string $file;

    public function __construct(protected string $root)
    {
        $this->file = $this->root . '/storage/media/categories.json';
    }

    public function all(): array
    {
        if (!file_exists($this->file)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($this->file), true);

        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $index => $category) {
            $data[$index] = $this->normalize($category);
        }

        usort($data, static fn(array $a, array $b): int => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

        return $data;
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $category) {
            if ((string)($category['id'] ?? '') === $id) {
                return $category;
            }
        }

        return null;
    }

    public function create(string $label, string $description = ''): array
    {
        $label = trim($label);

        if ($label === '') {
            throw new \RuntimeException('Kategoriename fehlt.');
        }

        $id = $this->slug($label);
        $categories = $this->all();

        $base = $id;
        $counter = 2;

        while ($this->containsId($categories, $id)) {
            $id = $base . '-' . $counter;
            $counter++;
        }

        $category = [
            'id' => $id,
            'label' => $label,
            'description' => trim($description),
            'icon' => 'folder',
            'color' => '',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        $categories[] = $category;
        $this->saveAll($categories);

        return $category;
    }

    public function update(string $id, array $values): array
    {
        $categories = $this->all();
        $found = false;
        $updated = null;

        foreach ($categories as $index => $category) {
            if ((string)($category['id'] ?? '') !== $id) {
                continue;
            }

            $label = trim((string)($values['label'] ?? $category['label'] ?? ''));

            if ($label === '') {
                throw new \RuntimeException('Kategoriename fehlt.');
            }

            $category['label'] = $label;
            $category['description'] = trim((string)($values['description'] ?? ''));
            $category['icon'] = trim((string)($values['icon'] ?? 'folder'));
            $category['color'] = trim((string)($values['color'] ?? ''));
            $category['updated_at'] = date('c');

            $categories[$index] = $this->normalize($category);
            $updated = $categories[$index];
            $found = true;
            break;
        }

        if (!$found || !$updated) {
            throw new \RuntimeException('Kategorie nicht gefunden.');
        }

        $this->saveAll($categories);

        return $updated;
    }

    public function delete(string $id, array $mediaItems): void
    {
        if ($id === '') {
            throw new \RuntimeException('Kategorie fehlt.');
        }

        foreach ($mediaItems as $item) {
            if ((string)($item['category'] ?? '') === $id) {
                throw new \RuntimeException('Kategorie kann nicht gelöscht werden, weil noch Medien zugeordnet sind.');
            }
        }

        $categories = array_values(array_filter(
            $this->all(),
            static fn(array $category): bool => (string)($category['id'] ?? '') !== $id
        ));

        $this->saveAll($categories);
    }

    public function labelFor(string $id): string
    {
        $category = $this->find($id);

        return $category ? (string)($category['label'] ?? $id) : $id;
    }

    public function counts(array $mediaItems): array
    {
        $counts = [
            '' => 0,
        ];

        foreach ($mediaItems as $item) {
            $category = (string)($item['category'] ?? '');
            $counts[$category] = ($counts[$category] ?? 0) + 1;

            if ($category === '') {
                $counts['']++;
            }
        }

        return $counts;
    }

    protected function saveAll(array $categories): void
    {
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0775, true);
        }

        file_put_contents(
            $this->file,
            json_encode(array_values($categories), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function containsId(array $categories, string $id): bool
    {
        foreach ($categories as $category) {
            if ((string)($category['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }

    protected function normalize(array $category): array
    {
        $category['id'] = (string)($category['id'] ?? $this->slug((string)($category['label'] ?? 'category')));
        $category['label'] = (string)($category['label'] ?? $category['id']);
        $category['description'] = (string)($category['description'] ?? '');
        $category['icon'] = (string)($category['icon'] ?? 'folder');
        $category['color'] = (string)($category['color'] ?? '');
        $category['created_at'] = (string)($category['created_at'] ?? date('c'));
        $category['updated_at'] = (string)($category['updated_at'] ?? $category['created_at']);

        return $category;
    }

    protected function slug(string $value): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'category';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'category';
    }
}
PHP);

    $write($root . '/public/admin/media/category.php', <<<'PHP'
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
PHP);

    $mediaIndex = $root . '/public/admin/media/index.php';

    if (file_exists($mediaIndex)) {
        $content = file_get_contents($mediaIndex);

        $old = <<<'PHP'
    $sidebar .= '<a class="' . ($filter === $id ? 'active' : '') . '" href="/admin/media/?category=' . e($id) . '" data-category-drop="' . e($id) . '">'
        . '<span>' . e($category['label'] ?? $id) . '</span>'
        . '<span class="tf-media-count">' . e($counts[$id] ?? 0) . '</span>'
        . '</a>';
PHP;

        $new = <<<'PHP'
    $sidebar .= '<div class="tf-media-category-row">'
        . '<a class="' . ($filter === $id ? 'active' : '') . '" href="/admin/media/?category=' . e($id) . '" data-category-drop="' . e($id) . '">'
        . '<span>' . e($category['label'] ?? $id) . '</span>'
        . '<span class="tf-media-count">' . e($counts[$id] ?? 0) . '</span>'
        . '</a>'
        . '<a class="tf-media-category-edit" href="/admin/media/category.php?id=' . e($id) . '" title="Kategorie bearbeiten">✎</a>'
        . '</div>';
PHP;

        if (str_contains($content, $old)) {
            $content = str_replace($old, $new, $content);
        }

        $write($mediaIndex, $content);
    }

    $cssFile = $root . '/public/assets/css/media.css';
    $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';

    if (!str_contains($css, 'PATCH 066 MEDIA CATEGORY MANAGEMENT')) {
        $css .= <<<'CSS'

/* PATCH 066 MEDIA CATEGORY MANAGEMENT */

.tf-media-category-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 34px;
  gap: .35rem;
  align-items: center;
}

.tf-media-category-row > a:first-child {
  min-width: 0;
}

.tf-media-category-edit {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  border-radius: var(--tf-radius-sm, .5rem);
  color: var(--tf-text-muted, #64727D);
  text-decoration: none;
  border: 1px solid transparent;
}

.tf-media-category-edit:hover {
  color: var(--tf-color-secondary, #E2A900);
  border-color: var(--tf-border-default, #D7DDE2);
  background: var(--tf-bg-hover, #EAF1F5);
}

.tf-category-edit,
.tf-category-danger {
  max-width: 900px;
  margin-bottom: 1rem;
}

.tf-category-edit label {
  display: grid;
  gap: .35rem;
  margin-bottom: .85rem;
  font-weight: 560;
}

.tf-category-edit input,
.tf-category-edit textarea {
  display: block;
  width: 100%;
  border: 1px solid var(--tf-input-border, #D7DDE2);
  border-radius: var(--tf-radius-sm, .5rem);
  padding: .62rem .7rem;
  font: inherit;
  background: var(--tf-input-bg, #FFFFFF);
}

.tf-category-edit textarea {
  min-height: 110px;
  resize: vertical;
}

.tf-admin-button.danger {
  border-color: var(--tf-state-danger-border, #FFB8B8);
  background: var(--tf-state-danger-bg, #FFE7E7);
  color: var(--tf-state-danger-text, #C62828);
}

.tf-admin-button.danger:disabled {
  opacity: .55;
  cursor: not-allowed;
}
CSS;

        $write($cssFile, $css);
    }

    $write($root . '/docs/treeforge/56-media-category-management.md', <<<'MD'
# Media Category Management

Patch 066 ergänzt die Kategoriepflege.

## Neu

```text
/admin/media/category.php?id=...
```

## Funktionen

- Kategorie umbenennen
- Beschreibung ändern
- Icon vorbereiten
- Farbe vorbereiten
- Kategorie löschen
- Löschen blockiert, wenn Medien zugeordnet sind

## Sidebar

Kategorien bekommen einen Bearbeiten-Link.

```text
Blog 12 ✎
Hero 4 ✎
```

## Noch offen

- echtes Icon-Rendering
- Farbanzeige in Kategorie
- Parent/Child Baumstruktur
- Drag & Drop Sortierung der Kategorien
MD);

    $log('Patch 066 Media Category Management fertig');
};
