<?php
declare(strict_types=1);

namespace TreeForge\Admin;

class AdminMenu
{
    public static function items(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'href' => '/admin/',
                'icon' => '⌂',
                'key' => 'dashboard',
            ],
            [
                'label' => 'Explorer',
                'href' => '/explorer',
                'icon' => '🌳',
                'key' => 'explorer',
            ],
            [
                'label' => 'Archive',
                'href' => '/archives',
                'icon' => '📦',
                'key' => 'archives',
            ],
            [
                'label' => 'Page Settings',
                'href' => '/admin/page-settings/',
                'icon' => '🧭',
                'key' => 'page-settings',
            ],
            [
                'label' => 'Media',
                'href' => '/admin/media/',
                'icon' => '🖼',
                'key' => 'media',
            ],
            [
                'label' => 'Templates',
                'href' => '#',
                'icon' => '🎨',
                'key' => 'templates',
                'disabled' => true,
            ],
            [
                'label' => 'Nodes',
                'href' => '#',
                'icon' => '🧩',
                'key' => 'nodes',
                'disabled' => true,
            ],
            [
                'label' => 'Docs',
                'href' => '/docs-viewer/',
                'icon' => '📚',
                'key' => 'docs',
            ],
            [
                'label' => 'Settings',
                'href' => '/admin/settings/',
                'icon' => '⚙',
                'key' => 'settings',
            ],
        ];
    }
}