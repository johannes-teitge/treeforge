<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeFactory
{
    public static function create(array $data): Node
    {
        return match ($data['type'] ?? '') {

            'text' => new TextNode($data),

            default => throw new RuntimeException(
                'Unknown node type: ' .
                ($data['type'] ?? 'undefined')
            ),
        };
    }
}