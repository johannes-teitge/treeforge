<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;

class ImageNode extends Node
{
    public function src(): string
    {
        return (string)($this->data['src'] ?? '');
    }

    public function alt(): string
    {
        return (string)($this->data['alt'] ?? '');
    }

    public function caption(): string
    {
        return (string)($this->data['caption'] ?? '');
    }
}