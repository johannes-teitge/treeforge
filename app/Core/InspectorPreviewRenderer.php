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
            'markdown' => self::markdownPreview($node),
            default => [
                'kind' => 'none',
                'language' => '',
                'content' => '',
                'html' => '',
            ],
        };
    }

    protected static function codePreview(array $node, string $language): array
    {
        return [
            'kind' => 'code',
            'language' => $language,
            'content' => (string)($node['content'] ?? ''),
            'html' => '',
        ];
    }

    protected static function markdownPreview(array $node): array
    {
        $content = (string)($node['content'] ?? '');

        return [
            'kind' => 'markdown',
            'language' => 'markdown',
            'content' => $content,
            'html' => MarkdownRenderer::toHtml($content),
        ];
    }
}