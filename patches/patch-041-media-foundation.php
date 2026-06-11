<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 041
 * Media Foundation
 *
 * Ziel:
 * - Media-Struktur vorbereiten: originals, meta, cache
 * - MediaManager, MediaScanner, MediaMeta, MediaConfig
 * - erste Admin-Seite /admin/media/
 * - Admin-Menü Media aktivieren
 * - noch kein Upload, noch kein Edit-Modal, noch kein Media Picker
 *
 * Dateien:
 * - app/Modules/Media/MediaConfig.php
 * - app/Modules/Media/MediaMeta.php
 * - app/Modules/Media/MediaScanner.php
 * - app/Modules/Media/MediaManager.php
 * - public/admin/media/index.php
 * - public/assets/css/media.css
 * - app/Admin/AdminMenu.php
 * - app/Admin/AdminLayout.php
 * - storage/media/originals/.gitkeep
 * - storage/media/meta/.gitkeep
 * - storage/media/cache/.gitkeep
 * - docs/treeforge/31-media-foundation.md
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

    $writeIfMissing = function (string $file, string $content = '') use ($log): void {
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

    $log('Patch 041 Media Foundation gestartet');

    $writeIfMissing($root . '/storage/media/originals/.gitkeep');
    $writeIfMissing($root . '/storage/media/meta/.gitkeep');
    $writeIfMissing($root . '/storage/media/cache/.gitkeep');

    $write($root . '/app/Modules/Media/MediaConfig.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

class MediaConfig
{
    public function __construct(
        protected string $root
    ) {
    }

    public function baseDir(): string
    {
        return $this->root . '/storage/media';
    }

    public function originalsDir(): string
    {
        return $this->baseDir() . '/originals';
    }

    public function metaDir(): string
    {
        return $this->baseDir() . '/meta';
    }

    public function cacheDir(): string
    {
        return $this->baseDir() . '/cache';
    }

    public function publicOriginalUrl(string $relativePath): string
    {
        return '/media/originals/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    public function allowedExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    }

    public function imageExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    }

    public function presets(): array
    {
        return [
            'thumbnail' => [
                'width' => 300,
                'height' => 300,
                'mode' => 'cover',
                'format' => 'webp',
            ],
            'card' => [
                'width' => 600,
                'height' => null,
                'mode' => 'contain',
                'format' => 'webp',
            ],
            'content' => [
                'width' => 900,
                'height' => null,
                'mode' => 'contain',
                'format' => 'webp',
            ],
            'hero' => [
                'width' => 1600,
                'height' => null,
                'mode' => 'contain',
                'format' => 'webp',
            ],
            'social' => [
                'width' => 1200,
                'height' => 630,
                'mode' => 'cover',
                'format' => 'webp',
            ],
        ];
    }
}
PHP);

    $write($root . '/app/Modules/Media/MediaMeta.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

class MediaMeta
{
    public function __construct(
        protected MediaConfig $config
    ) {
    }

    public function load(string $relativePath): array
    {
        $file = $this->metaFile($relativePath);

        if (!file_exists($file)) {
            return $this->defaults($relativePath);
        }

        $data = json_decode((string)file_get_contents($file), true);

        if (!is_array($data)) {
            return $this->defaults($relativePath);
        }

        return array_replace_recursive($this->defaults($relativePath), $data);
    }

    public function save(string $relativePath, array $meta): void
    {
        $file = $this->metaFile($relativePath);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        $meta['updated_at'] = date('c');

        file_put_contents(
            $file,
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function ensure(string $relativePath, array $fileInfo = []): array
    {
        $file = $this->metaFile($relativePath);

        if (file_exists($file)) {
            return $this->load($relativePath);
        }

        $meta = array_replace_recursive($this->defaults($relativePath), $fileInfo);
        $this->save($relativePath, $meta);

        return $meta;
    }

    public function defaults(string $relativePath): array
    {
        $filename = basename($relativePath);
        $id = $this->mediaId($relativePath);

        return [
            'id' => $id,
            'relative_path' => str_replace('\\', '/', $relativePath),
            'filename' => $filename,
            'title' => pathinfo($filename, PATHINFO_FILENAME),
            'alt' => '',
            'caption' => '',
            'description' => '',
            'category' => '',
            'tags' => [],
            'mime' => '',
            'size' => 0,
            'width' => null,
            'height' => null,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
    }

    public function mediaId(string $relativePath): string
    {
        $path = strtolower(str_replace(['\\', '/', '.', ' '], '-', $relativePath));
        $path = preg_replace('/[^a-z0-9_-]/', '-', (string)$path);
        $path = preg_replace('/-+/', '-', (string)$path);
        return trim((string)$path, '-');
    }

    protected function metaFile(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));

        return $this->config->metaDir() . '/' . $relativePath . '.json';
    }
}
PHP);

    $write($root . '/app/Modules/Media/MediaScanner.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MediaScanner
{
    public function __construct(
        protected MediaConfig $config,
        protected MediaMeta $meta
    ) {
    }

    public function scan(): array
    {
        $dir = $this->config->originalsDir();

        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        $allowed = $this->config->allowedExtensions();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());

            if (!in_array($extension, $allowed, true)) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($dir))), '/');

            $fileInfo = $this->fileInfo($absolutePath, $relativePath);
            $meta = $this->meta->ensure($relativePath, $fileInfo);

            $items[] = array_replace_recursive($fileInfo, $meta, [
                'url' => $this->config->publicOriginalUrl($relativePath),
                'absolute_path' => $absolutePath,
            ]);
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)($a['filename'] ?? ''), (string)($b['filename'] ?? ''));
        });

        return $items;
    }

    protected function fileInfo(string $absolutePath, string $relativePath): array
    {
        $mime = function_exists('mime_content_type') ? (string)mime_content_type($absolutePath) : '';
        $size = filesize($absolutePath) ?: 0;
        $width = null;
        $height = null;

        $imageSize = @getimagesize($absolutePath);

        if (is_array($imageSize)) {
            $width = $imageSize[0] ?? null;
            $height = $imageSize[1] ?? null;
        }

        return [
            'relative_path' => $relativePath,
            'filename' => basename($relativePath),
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ];
    }
}
PHP);

    $write($root . '/app/Modules/Media/MediaManager.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

class MediaManager
{
    protected MediaConfig $config;
    protected MediaMeta $meta;
    protected MediaScanner $scanner;

    public function __construct(
        protected string $root
    ) {
        $this->config = new MediaConfig($root);
        $this->meta = new MediaMeta($this->config);
        $this->scanner = new MediaScanner($this->config, $this->meta);
    }

    public function ensureDirectories(): void
    {
        foreach ([
            $this->config->originalsDir(),
            $this->config->metaDir(),
            $this->config->cacheDir(),
        ] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    public function all(): array
    {
        $this->ensureDirectories();

        return $this->scanner->scan();
    }

    public function categories(array $items): array
    {
        $categories = [];

        foreach ($items as $item) {
            $category = trim((string)($item['category'] ?? ''));

            if ($category === '') {
                $category = 'Nicht einsortiert';
            }

            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }

        ksort($categories);

        return $categories;
    }

    public function stats(array $items): array
    {
        $totalSize = 0;

        foreach ($items as $item) {
            $totalSize += (int)($item['size'] ?? 0);
        }

        return [
            'count' => count($items),
            'total_size' => $totalSize,
            'categories' => count($this->categories($items)),
            'cache_size' => $this->directorySize($this->config->cacheDir()),
        ];
    }

    public function config(): MediaConfig
    {
        return $this->config;
    }

    protected function directorySize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
PHP);

    $write($root . '/public/assets/css/media.css', <<<'CSS'
.tf-media-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1rem;
}

.tf-media-toolbar h2 {
  margin: 0;
  color: var(--tf-green);
}

.tf-media-toolbar p {
  margin: .25rem 0 0;
  color: var(--tf-muted);
}

.tf-media-grid-shell {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 1rem;
}

.tf-media-sidebar,
.tf-media-panel {
  background: var(--tf-cream);
  border: 1px solid var(--tf-border);
  border-radius: 1.1rem;
  padding: 1rem;
  box-shadow: 0 1rem 2.8rem rgba(18, 26, 23, .05);
}

.tf-media-sidebar h3,
.tf-media-panel h3 {
  margin: 0 0 .85rem;
  color: var(--tf-green);
}

.tf-media-filter {
  display: grid;
  gap: .35rem;
}

.tf-media-filter a {
  display: flex;
  justify-content: space-between;
  gap: .75rem;
  padding: .65rem .75rem;
  border-radius: .75rem;
  color: var(--tf-dark);
  text-decoration: none;
  font-weight: 800;
}

.tf-media-filter a.active,
.tf-media-filter a:hover {
  background: rgba(216, 138, 34, .14);
  color: var(--tf-green);
}

.tf-media-count {
  min-width: 2rem;
  text-align: center;
  border-radius: 999px;
  background: rgba(23, 63, 53, .08);
  color: var(--tf-green);
  padding: .1rem .45rem;
}

.tf-media-stats {
  display: grid;
  gap: .55rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--tf-border);
}

.tf-media-stat {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  color: var(--tf-muted);
  font-weight: 750;
}

.tf-media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1rem;
}

.tf-media-card {
  background: #fff;
  border: 1px solid var(--tf-border);
  border-radius: 1rem;
  overflow: hidden;
}

.tf-media-preview {
  aspect-ratio: 4 / 3;
  background:
    linear-gradient(45deg, rgba(0,0,0,.035) 25%, transparent 25%),
    linear-gradient(-45deg, rgba(0,0,0,.035) 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, rgba(0,0,0,.035) 75%),
    linear-gradient(-45deg, transparent 75%, rgba(0,0,0,.035) 75%);
  background-size: 20px 20px;
  background-position: 0 0, 0 10px, 10px -10px, -10px 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tf-media-preview img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.tf-media-info {
  padding: .85rem;
}

.tf-media-info strong {
  display: block;
  color: var(--tf-green);
  margin-bottom: .2rem;
  overflow-wrap: anywhere;
}

.tf-media-info small {
  display: block;
  color: var(--tf-muted);
  line-height: 1.5;
}

.tf-media-actions {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
  padding: 0 .85rem .85rem;
}

.tf-media-button {
  display: inline-flex;
  padding: .5rem .65rem;
  border-radius: .65rem;
  background: var(--tf-green);
  color: #fff;
  text-decoration: none;
  font-size: .9rem;
  font-weight: 850;
}

.tf-media-button.secondary {
  background: #fff;
  color: var(--tf-green);
  border: 1px solid var(--tf-border);
}

.tf-media-empty {
  background: #fff;
  border: 1px dashed rgba(23,63,53,.25);
  border-radius: 1rem;
  padding: 2rem;
  color: var(--tf-muted);
  font-weight: 800;
  text-align: center;
}

@media (max-width: 1000px) {
  .tf-media-grid-shell {
    grid-template-columns: 1fr;
  }
}
CSS);

    $write($root . '/public/admin/media/index.php', <<<'PHP'
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
PHP);

    $layoutFile = $root . '/app/Admin/AdminLayout.php';
    if (file_exists($layoutFile)) {
        $layout = file_get_contents($layoutFile);

        if (!str_contains($layout, 'media.css')) {
            $old = <<<'PHP'
            . '<link rel="stylesheet" href="/assets/css/page-settings.css">'
PHP;

            $new = <<<'PHP'
            . '<link rel="stylesheet" href="/assets/css/page-settings.css">'
            . '<link rel="stylesheet" href="/assets/css/media.css">'
PHP;

            if (str_contains($layout, $old)) {
                $layout = str_replace($old, $new, $layout);
                $write($layoutFile, $layout);
            }
        }
    }

    $adminMenuFile = $root . '/app/Admin/AdminMenu.php';
    if (file_exists($adminMenuFile)) {
        $menu = file_get_contents($adminMenuFile);

        $old = <<<'PHP'
            [
                'label' => 'Media',
                'href' => '#',
                'icon' => '🖼',
                'key' => 'media',
                'disabled' => true,
            ],
PHP;

        $new = <<<'PHP'
            [
                'label' => 'Media',
                'href' => '/admin/media/',
                'icon' => '🖼',
                'key' => 'media',
            ],
PHP;

        if (str_contains($menu, $old)) {
            $menu = str_replace($old, $new, $menu);
            $write($adminMenuFile, $menu);
        }
    }

    $write($root . '/docs/treeforge/31-media-foundation.md', <<<'MD'
# Media Foundation

Patch 041 ergänzt die Grundlage für die Medienverwaltung.

## Struktur

```text
storage/media/
├── originals/
├── meta/
└── cache/
```

## Idee

TreeForge speichert Originale dauerhaft.

Abgeleitete Größen werden später nur bei Bedarf erzeugt und im Cache gespeichert.

```text
Original
↓
Meta
↓
Render Cache
```

Der Cache ist wegwerfbar und kann jederzeit neu aufgebaut werden.

## Dateien

```text
app/Modules/Media/MediaConfig.php
app/Modules/Media/MediaMeta.php
app/Modules/Media/MediaScanner.php
app/Modules/Media/MediaManager.php
public/admin/media/index.php
public/assets/css/media.css
```

## Route

```text
/admin/media/
```

## Noch nicht enthalten

- Upload
- Edit Modal
- Kategorieverwaltung
- Media Picker
- Render-API für Derivate

Diese Funktionen folgen in späteren Patches.
MD);

    $log('Patch 041 Media Foundation fertig');
};
