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