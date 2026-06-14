<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class HtmlNode extends Node
{
    public function html(): string
    {
        $properties = $this->data['properties'] ?? [];
        $content = is_array($properties) ? ($properties['content'] ?? []) : [];
        $content = is_array($content) ? $content : [];

        return (string)(
            $content['html']
            ?? $content['content']
            ?? $this->data['html']
            ?? $this->data['content']
            ?? ''
        );
    }
}