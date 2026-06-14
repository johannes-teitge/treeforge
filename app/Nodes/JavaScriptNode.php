<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class JavaScriptNode extends Node
{
    public function script(): string
    {
        $properties = $this->data['properties'] ?? [];
        $content = is_array($properties) ? ($properties['content'] ?? []) : [];
        $content = is_array($content) ? $content : [];

        return (string)(
            $content['javascript']
            ?? $content['js']
            ?? $content['script']
            ?? $content['content']
            ?? $this->data['javascript']
            ?? $this->data['js']
            ?? $this->data['script']
            ?? $this->data['content']
            ?? ''
        );
    }
}