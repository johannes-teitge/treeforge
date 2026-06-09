<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class CssNode extends Node
{
    public function content(): string
    {
        return (string)($this->data['content'] ?? '');
    }
}