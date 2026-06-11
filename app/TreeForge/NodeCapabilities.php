<?php
declare(strict_types=1);

namespace App\TreeForge;

final class NodeCapabilities
{
    public static function defaults(): array
    {
        return [
            'renderable' => true,
            'editable' => true,
            'sortable' => true,
            'cloneable' => true,
            'deletable' => true,
        ];
    }

    public static function root(): array
    {
        return [
            'renderable' => true,
            'editable' => false,
            'sortable' => false,
            'cloneable' => false,
            'deletable' => false,
        ];
    }
}