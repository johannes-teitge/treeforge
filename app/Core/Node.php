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
        return $this->data['type'] ?? 'unknown';
    }

    public function data(): array
    {
        return $this->data;
    }
}