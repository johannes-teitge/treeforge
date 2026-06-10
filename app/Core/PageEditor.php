<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class PageEditor
{
    public static function updateTextNodeContent(array &$pageData, string $nodeId, string $content): array
    {
        return self::updateNodeContent($pageData, $nodeId, $content, ['text']);
    }

    public static function updateMarkdownNodeContent(array &$pageData, string $nodeId, string $content): array
    {
        return self::updateNodeContent($pageData, $nodeId, $content, ['markdown']);
    }

    public static function updateNodeContent(array &$pageData, string $nodeId, string $content, array $allowedTypes): array
    {
        if (!isset($pageData['children']) || !is_array($pageData['children'])) {
            throw new RuntimeException('Page has no children array');
        }

        foreach ($pageData['children'] as $index => $node) {
            if (!is_array($node)) {
                continue;
            }

            $updatedNode = self::updateNodeContentRecursive($pageData['children'][$index], $nodeId, $content, $allowedTypes);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        throw new RuntimeException("Node not found: {$nodeId}");
    }

    protected static function updateNodeContentRecursive(array &$node, string $nodeId, string $content, array $allowedTypes): ?array
    {
        if ((string)($node['id'] ?? '') === $nodeId) {
            $type = (string)($node['type'] ?? '');

            if (!in_array($type, $allowedTypes, true)) {
                throw new RuntimeException("Node type is not editable here: {$nodeId} / {$type}");
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

            $updatedNode = self::updateNodeContentRecursive($node['children'][$index], $nodeId, $content, $allowedTypes);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        return null;
    }
}