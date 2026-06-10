<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class PageEditor
{
    public static function updateTextNodeContent(array &$pageData, string $nodeId, string $content): array
    {
        if (!isset($pageData['children']) || !is_array($pageData['children'])) {
            throw new RuntimeException('Page has no children array');
        }

        foreach ($pageData['children'] as $index => $node) {
            if (!is_array($node)) {
                continue;
            }

            $updatedNode = self::updateTextNodeRecursive($pageData['children'][$index], $nodeId, $content);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        throw new RuntimeException("Node not found: {$nodeId}");
    }

    protected static function updateTextNodeRecursive(array &$node, string $nodeId, string $content): ?array
    {
        if ((string)($node['id'] ?? '') === $nodeId) {
            if ((string)($node['type'] ?? '') !== 'text') {
                throw new RuntimeException("Node is not editable as text: {$nodeId}");
            }

            $node['content'] = $content;
            $node['updated_at'] = date('c');

            return $node;
        }

        if (!isset($node['children']) || !is_array($node['children'])) {
            return null;
        }

        foreach ($node['children'] as $index => $child) {
            if (!is_array($child)) {
                continue;
            }

            $updatedNode = self::updateTextNodeRecursive($node['children'][$index], $nodeId, $content);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        return null;
    }
}