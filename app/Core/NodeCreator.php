<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeCreator
{
    public const ALLOWED_TYPES = [
        'heading',
        'codeblock',
        'text',
        'image',
        'button',
        'markdown',
        'css',
        'pagemenu',
        'menuitem',
        'columns',
    ];

    public static function createNode(string $type, array $options = []): array
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new RuntimeException("Unsupported node type: {$type}");
        }

        $usedIds = [];
        if (isset($options['_existing_ids']) && is_array($options['_existing_ids'])) {
            $usedIds = array_values(array_map('strval', $options['_existing_ids']));
        }

        $id = NodeIdGenerator::generateFromIds($usedIds);

        return match ($type) {
            'codeblock' => [
                'id' => $id,
                'type' => 'codeblock',
                'title' => 'Neuer Code-Block',
                'properties' => [
                    'content' => [
                        'code' => "<?php\necho 'Hallo TreeForge';\n",
                        'language' => 'php',
                        'caption' => '',
                        'show_line_numbers' => '1',
                        'wrap' => '0',
                    ],
                    'layout' => [],
                    'spacing' => [],
                    'design' => [],
                    'behavior' => [],
                    'advanced' => [],
                    'custom_css' => '',
                ],
                'children' => [],
            ],
            'heading' => [
                'id' => $id,
                'type' => 'heading',
                'title' => 'Neue Überschrift',
                'properties' => [
                    'content' => [
                        'text' => 'Neue Überschrift',
                        'level' => 'h2',
                    ],
                    'layout' => [],
                    'spacing' => [],
                    'design' => [],
                    'behavior' => [],
                    'advanced' => [],
                    'custom_css' => '',
                ],
                'children' => [],
            ],

            'text' => [
                'id' => $id,
                'type' => 'text',
                'content' => 'Neuer Text',
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

            'pagemenu' => [
                'id' => $id,
                'type' => 'pagemenu',
                'title' => 'Seitenmenü',
                'properties' => [
                    'content' => [
                        'mode' => 'manual',
                        'variant' => 'vertical',
                        'title' => 'Auf dieser Seite',
                        'show_title' => '1',
                        'sticky' => '0',
                        'behavior' => 'static',
                        'button_label' => 'Menü öffnen',
                        'button_icon' => '☰',
                        'active_mode' => 'none',
                        'empty_message' => 'Keine Menüpunkte.',
                        'heading_levels' => 'h2,h3',
                        'exclude_heading_ids' => '',
                        'manual_position' => 'after',
                    ],
                    'layout' => [],
                    'spacing' => [],
                    'design' => [],
                    'behavior' => [],
                    'advanced' => [],
                    'custom_css' => '',
                ],
                'children' => [],
            ],

            'menuitem' => [
                'id' => $id,
                'type' => 'menuitem',
                'title' => 'Menüpunkt',
                'properties' => [
                    'content' => [
                        'label' => 'Menüpunkt',
                        'href' => '#',
                        'target' => '_self',
                        'description' => '',
                        'icon' => '',
                        'badge' => '',
                        'rel' => '',
                        'aria_label' => '',
                        'item_type' => 'link',
                    ],
                    'layout' => [],
                    'spacing' => [],
                    'design' => [],
                    'behavior' => [],
                    'advanced' => [],
                    'custom_css' => '',
                ],
                'children' => [],
            ],

            'columns' => self::createColumnsNode($id, $options, $usedIds),

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

    /**
     * @param array<int,string> $usedIds
     */
    protected static function createColumnsNode(string $id, array $options, array &$usedIds): array
    {
        $columns = (int)($options['columns'] ?? 2);
        $columns = max(2, min(6, $columns));

        $gap = trim((string)($options['gap'] ?? '1rem'));
        $gap = $gap !== '' ? $gap : '1rem';

        $children = [];

        for ($i = 1; $i <= $columns; $i++) {
            $children[] = [
                'id' => NodeIdGenerator::generateFromIds($usedIds),
                'type' => 'column',
                'title' => 'Spalte ' . $i,
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
}