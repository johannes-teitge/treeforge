<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class PageEditor
{
    public static function updateTextNodeContent(array &$pageData, string $nodeId, string $content): bool
    {
        foreach ($pageData['children'] ?? [] as &$node) {
            if (!is_array($node)) {
                continue;
            }

            if (self::updateTextNodeRecursive($node, $nodeId, $content)) {
                return true;
            }
        }

        return false;
    }

    protected static function updateTextNodeRecursive(array &$node, string $nodeId, string $content): bool
    {
        if (($node['id'] ?? '') === $nodeId) {
            if (($node['type'] ?? '') !== 'text') {
                throw new RuntimeException("Node is not editable as text: {$nodeId}");
            }

            $node['content'] = $content;
            $node['updated_at'] = date('c');

            return true;
        }

        foreach ($node['children'] ?? [] as &$child) {
            if (is_array($child) && self::updateTextNodeRecursive($child, $nodeId, $content)) {
                return true;
            }
        }

        return false;
    }
}