<?php
declare(strict_types=1);

namespace TreeForge\Core;

class Page
{
    protected array $data = [];

    public function __construct(string $file)
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("Page not found: {$file}");
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid page JSON: {$file}");
        }

        $this->data = $data;
    }

    public function title(): string
    {
        return (string)($this->data['title'] ?? 'Untitled');
    }

    public function children(): array
    {
        return $this->data['children'] ?? [];
    }

    public function all(): array
    {
        return $this->data;
    }
}