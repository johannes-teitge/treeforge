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