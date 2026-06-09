<?php
declare(strict_types=1);

namespace TreeForge\Core;

class Config
{
    protected array $data = [];

    public function __construct(string $file)
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("Config not found: {$file}");
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid config JSON: {$file}");
        }

        $this->data = $data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }
}