<?php
declare(strict_types=1);

namespace App\TreeForge\Nodes;

use App\TreeForge\AbstractTreeForgeNode;

final class DemoNode extends AbstractTreeForgeNode
{
    public string $type = 'demo';
    public string $label = 'Demo Node';
    public string $icon = 'fa-solid fa-puzzle-piece';
    public string $category = 'Demo';
    public string $version = '1.0.0';

    public bool $hasChildren = false;

    public function getDefaultData(): array
    {
        return [
            'headline' => 'Demo Überschrift',
            'text' => 'Demo Text',
            'style' => 'info',
            'show_icon' => true,
        ];
    }

    public function getEditorSchema(): array
    {
        return [
            [
                'tab' => 'Inhalt',
                'fields' => [
                    [
                        'name' => 'headline',
                        'label' => 'Überschrift',
                        'type' => 'text',
                    ],
                    [
                        'name' => 'text',
                        'label' => 'Text',
                        'type' => 'textarea',
                    ],
                ],
            ],
            [
                'tab' => 'Design',
                'fields' => [
                    [
                        'name' => 'style',
                        'label' => 'Darstellung',
                        'type' => 'select',
                        'options' => [
                            'info' => 'Info',
                            'success' => 'Erfolg',
                            'warning' => 'Warnung',
                            'danger' => 'Fehler',
                        ],
                    ],
                    [
                        'name' => 'show_icon',
                        'label' => 'Icon anzeigen',
                        'type' => 'checkbox',
                    ],
                ],
            ],
        ];
    }

    public function getAssets(): array
    {
        return [
            'editor_css' => null,
            'frontend_css' => null,
            'frontend_js' => null,
        ];
    }

    public function render(array $data, array $children = []): string
    {
        $headline = htmlspecialchars((string)($data['headline'] ?? ''), ENT_QUOTES, 'UTF-8');
        $text = nl2br(htmlspecialchars((string)($data['text'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $style = htmlspecialchars((string)($data['style'] ?? 'info'), ENT_QUOTES, 'UTF-8');

        return '<div class="tf-node-demo tf-demo-' . $style . '">' .
            '<h3>' . $headline . '</h3>' .
            '<p>' . $text . '</p>' .
            '</div>';
    }
}