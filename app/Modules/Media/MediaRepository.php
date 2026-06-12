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

    public function publicUrlWithVersion(array $item): string
    {
        $url = $this->publicUrl($item);
        $version = $this->fileVersion($item);

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($version);
    }

    public function fileVersion(array $item): string
    {
        $relativePath = ltrim(str_replace('\\', '/', (string)($item['relative_path'] ?? '')), '/');
        $file = rtrim($this->config->originalsDir(), '/\\') . '/' . $relativePath;

        if (is_file($file)) {
            return (string)filemtime($file);
        }

        return (string)($item['updated_at'] ?? $item['replaced_at'] ?? $item['uploaded_at'] ?? time());
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