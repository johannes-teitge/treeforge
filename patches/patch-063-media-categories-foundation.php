<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 063
 * Media Categories Foundation
 *
 * Ziel:
 * - Media-Kategorien zentral verwalten
 * - Kategorien im Media Manager links anzeigen
 * - neue Kategorie anlegen
 * - Medien nach Kategorie filtern
 * - Kategorie am Medium speichern
 *
 * Noch nicht:
 * - Drag & Drop in Kategorien
 * - Baumstruktur mit Parent/Child
 * - Kategorie löschen/umbenennen UI
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

    $writeIfMissing = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            $log("Datei existiert bereits, übersprungen: {$file}");
            return;
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 063 Media Categories Foundation gestartet');

    $writeIfMissing($root . '/storage/media/categories.json', json_encode([
        [
            'id' => 'logos',
            'label' => 'Logos',
            'description' => 'Logos, Signets und Markenbilder',
            'created_at' => date('c'),
        ],
        [
            'id' => 'hero',
            'label' => 'Hero',
            'description' => 'Header- und Hero-Bilder',
            'created_at' => date('c'),
        ],
        [
            'id' => 'blog',
            'label' => 'Blog',
            'description' => 'Bilder für Blogartikel und News',
            'created_at' => date('c'),
        ],
        [
            'id' => 'downloads',
            'label' => 'Downloads',
            'description' => 'PDFs, ZIPs und Download-Dateien',
            'created_at' => date('c'),
        ],
        [
            'id' => 'social',
            'label' => 'Social Media',
            'description' => 'OpenGraph- und Social-Media-Bilder',
            'created_at' => date('c'),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

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
            'created_at' => date('c'),
        ];

        $categories[] = $category;
        $this->saveAll($categories);

        return $category;
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

    $write($root . '/public/admin/media/index.php', <<<'PHP'
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
    . '<a class="' . ($filter === '_none' ? 'active' : '') . '" href="/admin/media/?category=_none"><span>Nicht einsortiert</span><span class="tf-media-count">' . e($counts[''] ?? 0) . '</span></a>'
    . '</nav>'
    . '<h3>Kategorien</h3>'
    . '<nav class="tf-media-filter">';

foreach ($categoryList as $category) {
    $id = (string)($category['id'] ?? '');
    $sidebar .= '<a class="' . ($filter === $id ? 'active' : '') . '" href="/admin/media/?category=' . e($id) . '">'
        . '<span>' . e($category['label'] ?? $id) . '</span>'
        . '<span class="tf-media-count">' . e($counts[$id] ?? 0) . '</span>'
        . '</a>';
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

        $grid .= '<article class="tf-media-card">'
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
PHP);

    $editFile = $root . '/public/admin/media/edit.php';
    if (file_exists($editFile)) {
        $edit = file_get_contents($editFile);

        if (!str_contains($edit, 'use TreeForge\\Modules\\Media\\MediaCategoryRepository;')) {
            $edit = str_replace(
                'use TreeForge\\Modules\\Media\\MediaRepository;',
                "use TreeForge\\Modules\\Media\\MediaRepository;\nuse TreeForge\\Modules\\Media\\MediaCategoryRepository;",
                $edit
            );
        }

        if (!str_contains($edit, '$categories = new MediaCategoryRepository($root);')) {
            $edit = str_replace(
                '$repo = new MediaRepository($root);',
                "$repo = new MediaRepository($root);\n\$categories = new MediaCategoryRepository(\$root);",
                $edit
            );
        }

        if (!str_contains($edit, '$categoryOptions =')) {
            $edit = str_replace(
                '$tags = implode(\', \', (array)($item[\'tags\'] ?? []));',
                '$tags = implode(\', \', (array)($item[\'tags\'] ?? []));' . "\n"
                . '$categoryOptions = \'<option value="">Nicht einsortiert</option>\';' . "\n"
                . 'foreach ($categories->all() as $category) {' . "\n"
                . '    $catId = (string)($category[\'id\'] ?? \'\');' . "\n"
                . '    $selected = $catId === (string)($item[\'category\'] ?? \'\') ? \' selected\' : \'\';' . "\n"
                . '    $categoryOptions .= \'<option value="\' . e($catId) . \'"\' . $selected . \'>\' . e($category[\'label\'] ?? $catId) . \'</option>\';' . "\n"
                . '}',
                $edit
            );
        }

        $edit = str_replace(
            '<label><span>Kategorie</span><input type="text" name="category" value="\' . e($item[\'category\'] ?? \'\') . \'"></label>',
            '<label><span>Kategorie</span><select name="category">\' . $categoryOptions . \'</select></label>',
            $edit
        );

        $write($editFile, $edit);
    }

    $cssFile = $root . '/public/assets/css/media.css';
    $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';

    if (!str_contains($css, 'PATCH 063 MEDIA CATEGORIES')) {
        $css .= <<<'CSS'

/* PATCH 063 MEDIA CATEGORIES */

.tf-media-category-form {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--tf-border-soft, #E5E9EC);
}

.tf-media-category-form label {
  display: grid;
  gap: .3rem;
  margin-bottom: .65rem;
  font-weight: 500;
}

.tf-media-category-form input {
  width: 100%;
  border: 1px solid var(--tf-input-border, #D7DDE2);
  border-radius: var(--tf-radius-sm, .5rem);
  padding: .55rem .65rem;
  font: inherit;
}

.tf-media-category-form button {
  width: 100%;
  justify-content: center;
}

.tf-media-edit-form select {
  display: block;
  width: 100%;
  min-width: 0;
  border: 1px solid var(--tf-input-border, #D7DDE2);
  border-radius: var(--tf-radius-sm, .5rem);
  padding: .62rem .7rem;
  font: inherit;
  background: var(--tf-input-bg, #FFFFFF);
  color: var(--tf-input-text, #071725);
}
CSS;

        $write($cssFile, $css);
    }

    $write($root . '/docs/treeforge/53-media-categories-foundation.md', <<<'MD'
# Media Categories Foundation

Patch 063 ergänzt erste Media-Kategorien.

## Datei

```text
storage/media/categories.json
```

## Standard-Kategorien

```text
Logos
Hero
Blog
Downloads
Social Media
```

## Media Manager

Links werden angezeigt:

```text
Alle Medien
Nicht einsortiert
Kategorien
```

Zusätzlich:

```text
Neue Kategorie anlegen
Zählung pro Kategorie
Filter nach Kategorie
```

## Media Edit

Kategorie kann nun über Select gewählt werden.

## Noch offen

- Kategorie löschen
- Kategorie umbenennen
- Drag & Drop
- Baumstruktur Parent/Child
MD);

    $log('Patch 063 Media Categories Foundation fertig');
};
