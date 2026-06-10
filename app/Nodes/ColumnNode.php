<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;
use TreeForge\Core\NodeFactory;

class ColumnNode extends Node
{
    public function width(): string
    {
        return (string)($this->data['width'] ?? 'auto');
    }

    public function children(): array
    {
        $nodes = [];

        foreach ($this->data['children'] ?? [] as $nodeData) {
            $nodes[] = NodeFactory::create($nodeData);
        }

        return $nodes;
    }
}