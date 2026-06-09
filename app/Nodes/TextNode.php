<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class TextNode extends Node
{
    public function content(): string
    {
        return (string)($this->data['content'] ?? '');
    }
}