<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class PageMenuNode extends Node
{
    public function mode(): string
    {
        $mode = strtolower(trim((string)($this->content()['mode'] ?? 'manual')));
        return in_array($mode, ['manual', 'headings', 'hybrid'], true) ? $mode : 'manual';
    }

    public function variant(): string
    {
        $variant = strtolower(trim((string)($this->content()['variant'] ?? 'vertical')));
        $variant = preg_replace('/[^a-z0-9_-]/', '', $variant) ?: 'vertical';

        return match ($variant) {
            'sidebar' => 'vertical',
            'h', 'hor', 'horizontal-menu' => 'horizontal',
            'button', 'buttonbar' => 'buttons',
            'source', 'references' => 'sources',
            default => in_array($variant, ['vertical', 'horizontal', 'buttons', 'pills', 'sources', 'compact'], true) ? $variant : 'vertical',
        };
    }

    public function behavior(): string
    {
        $content = $this->content();
        $behavior = strtolower(trim((string)($content['behavior'] ?? 'static')));

        if ($this->truthy($content['sticky'] ?? false)) {
            return 'sticky';
        }

        return in_array($behavior, ['static', 'sticky', 'popup', 'dropdown'], true) ? $behavior : 'static';
    }

    public function menuTitle(): string
    {
        $content = $this->content();
        return trim((string)($content['title'] ?? $this->get('title', 'Seitenmenü')));
    }

    public function showTitle(): bool
    {
        return $this->truthy($this->content()['show_title'] ?? true);
    }

    public function buttonLabel(): string
    {
        $label = trim((string)($this->content()['button_label'] ?? 'Menü öffnen'));
        return $label !== '' ? $label : 'Menü öffnen';
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

    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'ja', 'on', 'visible', 'show'], true);
    }
}