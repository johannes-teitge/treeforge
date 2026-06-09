<?php
declare(strict_types=1);

namespace TreeForge\Core;

class InspectorPreviewRenderer
{
    public static function render(array $node): array
    {
        $type = (string)($node['type'] ?? 'unknown');

        return match ($type) {
            'css' => self::codePreview($node, 'css'),
            'markdown' => self::codePreview($node, 'markdown'),
            default => [
                'kind' => 'none',
                'language' => '',
                'content' => '',
            ],
        };
    }

    protected static function codePreview(array $node, string $language): array
    {
        return [
            'kind' => 'code',
            'language' => $language,
            'content' => (string)($node['content'] ?? ''),
        ];
    }
}