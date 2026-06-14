<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class CodeBlockNode extends Node
{
    public function code(): string
    {
        $content = $this->contentData();

        return (string)(
            $content['code']
            ?? $this->data['code']
            ?? $this->data['content']
            ?? ''
        );
    }

    public function language(): string
    {
        $content = $this->contentData();
        $language = strtolower(trim((string)($content['language'] ?? $this->data['language'] ?? 'plaintext')));
        $language = preg_replace('/[^a-z0-9_+.-]/', '', $language) ?: 'plaintext';

        return $language;
    }

    public function caption(): string
    {
        $content = $this->contentData();

        return (string)($content['caption'] ?? $this->data['caption'] ?? '');
    }

    public function showLineNumbers(): bool
    {
        $content = $this->contentData();
        $value = $content['show_line_numbers'] ?? $this->data['show_line_numbers'] ?? false;

        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    public function wrap(): bool
    {
        $content = $this->contentData();
        $value = $content['wrap'] ?? $this->data['wrap'] ?? false;

        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
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