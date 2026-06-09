<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class MarkdownNode extends Node
{
    public function content(): string
    {
        return (string)($this->data['content'] ?? '');
    }
}