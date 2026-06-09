<?php
declare(strict_types=1);

namespace TreeForge\Nodes;

use TreeForge\Core\Node;
use TreeForge\Core\NodeFactory;

class ColumnsNode extends Node
{
    public function children(): array
    {
        $nodes = [];

        foreach ($this->data['children'] ?? [] as $nodeData) {
            $nodes[] = NodeFactory::create($nodeData);
        }

        return $nodes;
    }
}