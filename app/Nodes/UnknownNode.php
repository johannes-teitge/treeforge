<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class UnknownNode extends Node
{
    public function originalType(): string
    {
        return (string)($this->data['type'] ?? 'unknown');
    }
}