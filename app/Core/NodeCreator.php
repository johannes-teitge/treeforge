<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeCreator
{
    public const ALLOWED_TYPES = [
        'text',
        'image',
        'button',
        'markdown',
        'css',
        'columns',
    ];

    public static function createNode(string $type, array $options = []): array
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new RuntimeException("Unsupported node type: {$type}");
        }

        $id = self::id('node_' . $type);

        return match ($type) {
            'text' => [
                'id' => $id,
                'type' => 'text',
                'content' => "Neuer Text",
            ],

            'image' => [
                'id' => $id,
                'type' => 'image',
                'src' => '/assets/img/treeforge-demo.svg',
                'alt' => 'Neues Bild',
                'caption' => '',
            ],

            'button' => [
                'id' => $id,
                'type' => 'button',
                'label' => 'Neuer Button',
                'url' => '#',
                'variant' => 'primary',
            ],

            'markdown' => [
                'id' => $id,
                'type' => 'markdown',
                'content' => "# Neue Markdown Node\n\nHier kann **Markdown** geschrieben werden.",
            ],

            'css' => [
                'id' => $id,
                'type' => 'css',
                'content' => ".tf-custom {\n  color: #1E3D1C;\n}",
            ],

            'columns' => self::createColumnsNode($id, $options),

            default => throw new RuntimeException("Unsupported node type: {$type}"),
        };
    }

    public static function appendNode(array &$pageData, ?string $parentId, array $node): void
    {
        if ($parentId === null || $parentId === '' || $parentId === 'root' || $parentId === 'page') {
            if (!isset($pageData['children']) || !is_array($pageData['children'])) {
                $pageData['children'] = [];
            }

            $pageData['children'][] = $node;
            return;
        }

        if (!isset($pageData['children']) || !is_array($pageData['children'])) {
            throw new RuntimeException('Page has no children array');
        }

        foreach ($pageData['children'] as $index => $child) {
            if (!is_array($child)) {
                continue;
            }

            if (self::appendNodeRecursive($pageData['children'][$index], $parentId, $node)) {
                return;
            }
        }

        throw new RuntimeException("Parent node not found: {$parentId}");
    }

    protected static function appendNodeRecursive(array &$currentNode, string $parentId, array $node): bool
    {
        if ((string)($currentNode['id'] ?? '') === $parentId) {
            if (!isset($currentNode['children']) || !is_array($currentNode['children'])) {
                $currentNode['children'] = [];
            }

            $currentNode['children'][] = $node;
            return true;
        }

        if (!isset($currentNode['children']) || !is_array($currentNode['children'])) {
            return false;
        }

        foreach ($currentNode['children'] as $index => $child) {
            if (!is_array($child)) {
                continue;
            }

            if (self::appendNodeRecursive($currentNode['children'][$index], $parentId, $node)) {
                return true;
            }
        }

        return false;
    }

    protected static function createColumnsNode(string $id, array $options): array
    {
        $columns = (int)($options['columns'] ?? 2);
        $columns = max(2, min(6, $columns));

        $gap = trim((string)($options['gap'] ?? '1rem'));
        $gap = $gap !== '' ? $gap : '1rem';

        $children = [];

        for ($i = 1; $i <= $columns; $i++) {
            $children[] = [
                'id' => self::id('node_column_' . $i),
                'type' => 'column',
                'width' => '1fr',
                'children' => [],
            ];
        }

        return [
            'id' => $id,
            'type' => 'columns',
            'settings' => [
                'columns' => $columns,
                'gap' => $gap,
            ],
            'children' => $children,
        ];
    }

    protected static function id(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(4));
    }
}