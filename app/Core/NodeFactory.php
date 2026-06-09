<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeFactory
{
    public static function create(array $data): Node
    {
        $type = (string)($data['type'] ?? 'unknown');
        $class = NodeRegistry::resolve($type);

        if (!$class) {
            throw new RuntimeException("Unknown node type: {$type}");
        }

        return new $class($data);
    }
}