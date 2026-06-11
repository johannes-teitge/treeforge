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