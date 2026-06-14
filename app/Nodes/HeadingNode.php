<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class HeadingNode extends Node
{
    public function text(): string
    {
        $content = $this->contentData();

        return (string)(
            $content['text']
            ?? $this->data['text']
            ?? $this->data['content']
            ?? $this->data['title']
            ?? ''
        );
    }

    public function level(): string
    {
        $content = $this->contentData();
        $level = strtolower((string)($content['level'] ?? $this->data['level'] ?? 'h2'));

        return in_array($level, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $level : 'h2';
    }

    /**
     * @return array<string,mixed>
     */
    protected function contentData(): array
    {
        $properties = $this->data['properties'] ?? [];

        if (!is_array($properties)) {
            return [];
        }

        $content = $properties['content'] ?? [];

        return is_array($content) ? $content : [];
    }
}