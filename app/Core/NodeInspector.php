<?php
declare(strict_types=1);

namespace TreeForge\Core;

class NodeInspector
{
    public static function inspectArray(array $node): array
    {
        $properties = [];

        foreach ($node as $key => $value) {
            if (in_array($key, ['id', 'type', 'children'], true)) {
                continue;
            }

            $properties[$key] = $value;
        }

        return [
            'id' => (string)($node['id'] ?? ''),
            'type' => (string)($node['type'] ?? 'unknown'),
            'properties' => $properties,
            'preview' => InspectorPreviewRenderer::render($node),
            'has_children' => isset($node['children']) && is_array($node['children']) && $node['children'] !== [],
            'children_count' => isset($node['children']) && is_array($node['children']) ? count($node['children']) : 0,
            'raw' => $node,
        ];
    }

    public static function countNodes(array $pageOrNode): int
    {
        $count = 0;

        foreach ($pageOrNode['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }

            $count++;
            $count += self::countNodes($child);
        }

        return $count;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'text' => 'Text',
            'image' => 'Image',
            'button' => 'Button',
            'columns' => 'Columns',
            'column' => 'Column',
            'css' => 'CSS',
            'markdown' => 'Markdown',
            default => ucfirst($type),
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'text' => '📝',
            'image' => '🖼',
            'button' => '🔘',
            'columns' => '▦',
            'column' => '▥',
            'css' => '🎨',
            'markdown' => '⬇️',
            default => '📦',
        };
    }
}