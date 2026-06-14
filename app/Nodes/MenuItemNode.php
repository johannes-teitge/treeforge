<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class MenuItemNode extends Node
{
    public function label(): string
    {
        $content = $this->content();
        return trim((string)($content['label'] ?? $this->get('title', 'Menüpunkt')));
    }

    public function href(): string
    {
        $content = $this->content();
        $behavior = $this->behavior();
        return trim((string)($content['href'] ?? $behavior['url'] ?? $this->get('href', '#')));
    }

    public function target(): string
    {
        $content = $this->content();
        $behavior = $this->behavior();
        return trim((string)($content['target'] ?? $behavior['target'] ?? $this->get('target', '_self')));
    }

    public function icon(): string
    {
        return trim((string)($this->content()['icon'] ?? ''));
    }

    public function description(): string
    {
        return trim((string)($this->content()['description'] ?? ''));
    }

    public function badge(): string
    {
        return trim((string)($this->content()['badge'] ?? ''));
    }

    /** @return array<string,mixed> */
    protected function content(): array
    {
        $properties = $this->get('properties', []);
        if (!is_array($properties)) {
            return [];
        }

        $content = $properties['content'] ?? [];
        return is_array($content) ? $content : [];
    }

    /** @return array<string,mixed> */
    protected function behavior(): array
    {
        $properties = $this->get('properties', []);
        if (!is_array($properties)) {
            return [];
        }

        $behavior = $properties['behavior'] ?? [];
        return is_array($behavior) ? $behavior : [];
    }
}