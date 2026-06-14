<?php
declare(strict_types=1);

namespace TreeForge\Core;

use TreeForge\Nodes\UnknownNode;

class NodeFactory
{
    public static function create(array $data): Node
    {
        $type = (string)($data['type'] ?? 'unknown');
        $class = NodeRegistry::resolve($type);

        if (!$class) {
            return new UnknownNode($data);
        }

        return new $class($data);
    }
}