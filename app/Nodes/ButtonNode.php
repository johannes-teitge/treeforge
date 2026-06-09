<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class ButtonNode extends Node
{
    public function label(): string
    {
        return (string)($this->data['label'] ?? 'Button');
    }

    public function url(): string
    {
        return (string)($this->data['url'] ?? '#');
    }

    public function variant(): string
    {
        return (string)($this->data['variant'] ?? 'primary');
    }
}