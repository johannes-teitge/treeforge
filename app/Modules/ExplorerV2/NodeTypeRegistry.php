<?php
declare(strict_types=1);

namespace TreeForge\Modules\ExplorerV2;

class NodeTypeRegistry
{
    public function all(): array
    {
        return [
            'RootNode' => [
                'label' => 'Root',
                'icon' => '🌲',
                'allowed_children' => ['*'],
                'defaults' => ['type' => 'RootNode', 'title' => 'Root', 'children' => []],
            ],
            'TextNode' => [
                'label' => 'Text',
                'icon' => '📝',
                'allowed_children' => [],
                'defaults' => ['type' => 'TextNode', 'title' => 'Neuer Text', 'text' => ''],
            ],
            'HeadingNode' => [
                'label' => 'Überschrift',
                'icon' => '🔠',
                'allowed_children' => [],
                'defaults' => [
                    'type' => 'HeadingNode',
                    'title' => 'Neue Überschrift',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'editor_note' => '',
                    'properties' => [
                        'content' => [
                            'text' => 'Neue Überschrift',
                            'level' => 'h2',
                        ],
                        'layout' => [
                            'display' => 'block',
                            'alignment' => '',
                            'width' => '',
                            'max_width' => '',
                            'min_height' => '',
                            'columns' => '',
                        ],
                        'spacing' => [
                            'margin' => '',
                            'padding' => '',
                            'gap' => '',
                        ],
                        'design' => [
                            'background' => '',
                            'color' => '',
                            'border' => '',
                            'border_radius' => '',
                            'box_shadow' => '',
                            'style' => '',
                        ],
                        'behavior' => [
                            'url' => '',
                            'target' => '_self',
                            'zoom' => '',
                        ],
                        'advanced' => [
                            'css_class' => '',
                            'css_id' => '',
                            'custom_style' => '',
                        ],
                        'custom_css' => '',
                    ],
                    'children' => [],
                ],
            ],
            'CodeBlockNode' => [
                'label' => 'Code / Highlighter',
                'icon' => '💻',
                'allowed_children' => [],
                'defaults' => [
                    'type' => 'CodeBlockNode',
                    'title' => 'Neuer Code-Block',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'editor_note' => '',
                    'properties' => [
                        'content' => [
                            'code' => "<?php\necho 'Hallo TreeForge';\n",
                            'language' => 'php',
                            'caption' => '',
                            'show_line_numbers' => '1',
                            'wrap' => '0',
                        ],
                        'layout' => [
                            'display' => 'block',
                            'alignment' => '',
                            'width' => '',
                            'max_width' => '',
                            'min_height' => '',
                            'columns' => '',
                        ],
                        'spacing' => [
                            'margin' => '',
                            'padding' => '',
                            'gap' => '',
                        ],
                        'design' => [
                            'background' => '',
                            'color' => '',
                            'border' => '',
                            'border_radius' => '',
                            'box_shadow' => '',
                            'style' => '',
                        ],
                        'behavior' => [
                            'url' => '',
                            'target' => '_self',
                            'zoom' => '',
                        ],
                        'advanced' => [
                            'css_class' => '',
                            'css_id' => '',
                            'custom_style' => '',
                        ],
                        'custom_css' => '',
                    ],
                    'children' => [],
                ],
            ],
            'MarkdownNode' => [
                'label' => 'Markdown',
                'icon' => '⬇️',
                'allowed_children' => [],
                'defaults' => ['type' => 'MarkdownNode', 'title' => 'Neuer Markdown Block', 'markdown' => ''],
            ],
            'HtmlNode' => [
                'label' => 'HTML',
                'icon' => '📄',
                'allowed_children' => [],
                'defaults' => ['type' => 'HtmlNode', 'title' => 'Neuer HTML Block', 'html' => ''],
            ],
            'CssNode' => [
                'label' => 'CSS',
                'icon' => '🎨',
                'allowed_children' => [],
                'defaults' => ['type' => 'CssNode', 'title' => 'Neuer CSS Block', 'css' => ''],
            ],
            'ImageNode' => [
                'label' => 'Bild',
                'icon' => '🖼️',
                'allowed_children' => [],
                'defaults' => [
                    'type' => 'ImageNode', 'title' => 'Neues Bild', 'media_id' => '',
                    'alt' => '', 'caption' => '', 'display' => 'content',
                    'link_url' => '', 'link_target' => '_self',
                ],
            ],
            'ButtonNode' => [
                'label' => 'Button',
                'icon' => '🔘',
                'allowed_children' => [],
                'defaults' => ['type' => 'ButtonNode', 'title' => 'Neuer Button', 'label' => 'Mehr erfahren', 'url' => '', 'target' => '_self'],
            ],
            'ContainerNode' => [
                'label' => 'Container',
                'icon' => '📦',
                'allowed_children' => ['*'],
                'defaults' => [
                    'type' => 'ContainerNode',
                    'title' => 'Neuer Container',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'container' => [
                        'display' => 'block',
                        'width' => '',
                        'max_width' => '',
                        'min_height' => '',
                        'margin' => '',
                        'padding' => '',
                        'gap' => '',
                        'background' => '',
                        'border' => '',
                        'border_radius' => '',
                        'box_shadow' => '',
                        'css_class' => '',
                        'css_id' => '',
                        'custom_style' => '',
                    ],
                    'children' => [],
                ],
            ],
            'ColumnsNode' => [
                'label' => 'Columns',
                'icon' => '▦',
                'allowed_children' => ['ColumnNode'],
                'defaults' => [
                    'type' => 'ColumnsNode',
                    'title' => 'Neue Spalten',
                    'columns' => 2,
                    'gap' => '1rem',
                    'children' => [
                        ['type' => 'ColumnNode', 'title' => 'Spalte 1', 'children' => []],
                        ['type' => 'ColumnNode', 'title' => 'Spalte 2', 'children' => []],
                    ],
                ],
            ],
            'ColumnNode' => [
                'label' => 'Column',
                'icon' => '📄',
                'allowed_children' => ['*'],
                'defaults' => ['type' => 'ColumnNode', 'title' => 'Spalte', 'children' => []],
            ],
            'ScheduleContainerNode' => [
                'label' => 'Zeitgesteuerter Container',
                'icon' => '⏱️',
                'allowed_children' => ['*'],
                'defaults' => [
                    'type' => 'ScheduleContainerNode',
                    'title' => 'Zeitgesteuerter Container',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'container' => [
                        'display' => 'block',
                        'width' => '',
                        'max_width' => '',
                        'min_height' => '',
                        'margin' => '',
                        'padding' => '',
                        'gap' => '',
                        'background' => '',
                        'border' => '',
                        'border_radius' => '',
                        'box_shadow' => '',
                        'css_class' => '',
                        'css_id' => '',
                        'custom_style' => '',
                    ],
                    'schedule' => [
                        'active_from' => '',
                        'active_until' => '',
                        'days' => [],
                        'time_from' => '',
                        'time_until' => '',
                        'timezone' => 'Europe/Berlin',
                    ],
                    'children' => [],
                ],
            ],
            'PageMenuNode' => [
                'label' => 'Seitenmenü / Linkliste',
                'icon' => '☰',
                'allowed_children' => ['MenuItemNode'],
                'defaults' => [
                    'type' => 'PageMenuNode',
                    'title' => 'Seitenmenü',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'editor_note' => '',
                    'properties' => [
                        'content' => [
                            'mode' => 'manual',
                            'variant' => 'vertical',
                            'title' => 'Auf dieser Seite',
                            'show_title' => '1',
                            'sticky' => '0',
                            'heading_levels' => 'h2,h3',
                            'exclude_heading_ids' => '',
                            'manual_position' => 'after',
                        ],
                        'layout' => [
                            'display' => 'block',
                            'alignment' => '',
                            'width' => '',
                            'max_width' => '',
                            'min_height' => '',
                            'columns' => '',
                        ],
                        'spacing' => [
                            'margin' => '',
                            'padding' => '',
                            'gap' => '',
                        ],
                        'design' => [
                            'background' => '',
                            'color' => '',
                            'border' => '',
                            'border_radius' => '',
                            'box_shadow' => '',
                            'style' => '',
                        ],
                        'behavior' => [
                            'url' => '',
                            'target' => '_self',
                            'zoom' => '',
                        ],
                        'advanced' => [
                            'css_class' => '',
                            'css_id' => '',
                            'custom_style' => '',
                        ],
                        'custom_css' => '',
                    ],
                    'children' => [
                        [
                            'type' => 'MenuItemNode',
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
                    ],
                ],
            ],
            'MenuItemNode' => [
                'label' => 'Menüpunkt',
                'icon' => '↳',
                'allowed_children' => [],
                'defaults' => [
                    'type' => 'MenuItemNode',
                    'title' => 'Menüpunkt',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'editor_note' => '',
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
            ],            'ReferenceNode' => [
                'label' => 'Referenz',
                'icon' => '🔗',
                'allowed_children' => [],
                'defaults' => ['type' => 'ReferenceNode', 'title' => 'Referenz', 'source_node_id' => '', 'mode' => 'live'],
            ],
        ];
    }

    public function get(string $type): ?array
    {
        $all = $this->all();
        return $all[$type] ?? null;
    }

    public function defaults(string $type): array
    {
        $definition = $this->get($type);
        if (!$definition) {
            throw new \RuntimeException('Unbekannter Node-Typ: ' . $type);
        }
        return (array)($definition['defaults'] ?? ['type' => $type]);
    }

    public function canContain(string $parentType, string $childType): bool
    {
        $parent = $this->get($parentType);
        if (!$parent) {
            return false;
        }
        $allowed = (array)($parent['allowed_children'] ?? []);
        return in_array('*', $allowed, true) || in_array($childType, $allowed, true);
    }
}