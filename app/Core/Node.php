<?php
declare(strict_types=1);

namespace TreeForge\Core;

abstract class Node
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function type(): string
    {
        return (string)($this->data['type'] ?? 'unknown');
    }

    public function id(): string
    {
        return (string)($this->data['id'] ?? '');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function data(): array
    {
        return $this->data;
    }
}