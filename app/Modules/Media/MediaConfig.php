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
        return '/api/media/file.php?path=' . rawurlencode(ltrim(str_replace('\\', '/', $relativePath), '/'));
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