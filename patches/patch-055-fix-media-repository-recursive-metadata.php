<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 055
 * Fix Media Repository Recursive Metadata
 *
 * Problem:
 * - Neue Uploads liegen unter storage/media/meta/YYYY/MM/*.json
 * - MediaRepository fand nicht alle rekursiven Meta-Dateien zuverlässig
 * - Alte Metadaten ohne "kind" wurden als FILE angezeigt
 *
 * Fix:
 * - rekursive Suche per RecursiveDirectoryIterator
 * - robuste Normalisierung von kind anhand Extension/MIME
 * - findById funktioniert auch für neue Uploads
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

    $log('Patch 055 Fix Media Repository Recursive Metadata gestartet');

    $write($root . '/app/Modules/Media/MediaRepository.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class MediaRepository
{
    protected MediaConfig $config;

    public function __construct(protected string $root)
    {
        $this->config = new MediaConfig($root);
    }

    public function all(): array
    {
        $items = [];
        $metaDir = $this->config->metaDir();

        if (!is_dir($metaDir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($metaDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            $data = json_decode((string)file_get_contents($file->getPathname()), true);

            if (!is_array($data)) {
                continue;
            }

            $data['_meta_file'] = $file->getPathname();
            $items[] = $this->normalize($data);
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)($b['created_at'] ?? $b['uploaded_at'] ?? ''), (string)($a['created_at'] ?? $a['uploaded_at'] ?? ''));
        });

        return $items;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

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
            throw new RuntimeException('relative_path fehlt.');
        }

        $file = $this->metaFileForRelativePath($relativePath);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        unset($item['_meta_file']);

        $item = $this->normalize($item);
        $item['updated_at'] = date('c');

        file_put_contents(
            $file,
            json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
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
        $filename = (string)($item['filename'] ?? basename((string)($item['relative_path'] ?? '')));
        $extension = strtolower((string)($item['extension'] ?? pathinfo($filename, PATHINFO_EXTENSION)));
        $mime = strtolower((string)($item['mime'] ?? ''));

        if ($extension === '' && $filename !== '') {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        }

        $item['filename'] = $filename;
        $item['extension'] = $extension;

        if (empty($item['kind'])) {
            $item['kind'] = $this->detectKind($extension, $mime);
        }

        if (empty($item['mime']) && $extension === 'svg') {
            $item['mime'] = 'image/svg+xml';
        }

        $item['title'] = (string)($item['title'] ?? pathinfo($filename, PATHINFO_FILENAME));
        $item['alt'] = (string)($item['alt'] ?? '');
        $item['caption'] = (string)($item['caption'] ?? '');
        $item['description'] = (string)($item['description'] ?? '');
        $item['category'] = (string)($item['category'] ?? '');
        $item['tags'] = (array)($item['tags'] ?? []);
        $item['copyright'] = (string)($item['copyright'] ?? '');
        $item['photographer'] = (string)($item['photographer'] ?? '');
        $item['license'] = (string)($item['license'] ?? '');
        $item['focus_x'] = isset($item['focus_x']) ? (int)$item['focus_x'] : 50;
        $item['focus_y'] = isset($item['focus_y']) ? (int)$item['focus_y'] : 50;
        $item['featured'] = (bool)($item['featured'] ?? false);
        $item['usage_count'] = (int)($item['usage_count'] ?? 0);
        $item['last_used'] = $item['last_used'] ?? null;
        $item['versions'] = (array)($item['versions'] ?? []);

        return $item;
    }

    protected function detectKind(string $extension, string $mime): string
    {
        if ($extension === 'svg' || $mime === 'image/svg+xml') {
            return 'vector';
        }

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return 'image';
        }

        if (in_array($extension, ['pdf', 'docx', 'xlsx', 'txt', 'csv', 'odt'], true)) {
            return 'document';
        }

        if (in_array($extension, ['zip', 'rar', '7z'], true)) {
            return 'download';
        }

        return 'file';
    }
}
PHP);

    $write($root . '/docs/treeforge/45-fix-media-repository-recursive-metadata.md', <<<'MD'
# Fix Media Repository Recursive Metadata

Patch 055 repariert den MediaRepository-Zugriff.

## Fehler

Neue Uploads wurden nicht auf der Edit-Seite gefunden, obwohl sie im Grid sichtbar waren.

Ursache:

```text
storage/media/meta/YYYY/MM/*.json
```

wurde nicht zuverlässig rekursiv durchsucht.

Außerdem wurden ältere Metadaten ohne `kind` als `FILE` behandelt.

## Fix

- RecursiveDirectoryIterator für alle JSON-Metadaten
- robuste `kind`-Erkennung aus Extension und MIME-Type
- alte Metadaten bleiben kompatibel
- `findById()` funktioniert jetzt auch für neue Uploads
MD);

    $log('Patch 055 Fix Media Repository Recursive Metadata fertig');
};
