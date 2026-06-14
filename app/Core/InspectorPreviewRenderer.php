<?php
declare(strict_types=1);

namespace TreeForge\Core;

class InspectorPreviewRenderer
{
    public static function render(array $node): array
    {
        $type = (string)($node['type'] ?? 'unknown');

        return match ($type) {
            'codeblock', 'CodeBlockNode' => self::codeBlockPreview($node),
            'heading', 'HeadingNode' => self::headingPreview($node),
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

    protected static function codeBlockPreview(array $node): array
    {
        $content = (array)($node['properties']['content'] ?? []);
        $code = (string)($content['code'] ?? $node['code'] ?? $node['content'] ?? '');
        $language = strtolower(trim((string)($content['language'] ?? $node['language'] ?? 'plaintext')));
        $language = preg_replace('/[^a-z0-9_+#.-]/', '', $language) ?: 'plaintext';

        return [
            'kind' => 'code',
            'language' => $language,
            'content' => $code,
            'html' => '<pre><code class="language-' . htmlspecialchars($language, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>',
        ];
    }

    protected static function headingPreview(array $node): array
    {
        $content = (array)($node['properties']['content'] ?? []);
        $text = (string)($content['text'] ?? $node['content'] ?? $node['text'] ?? $node['title'] ?? '');
        $level = strtolower((string)($content['level'] ?? $node['level'] ?? 'h2'));

        if (!in_array($level, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $level = 'h2';
        }

        return [
            'kind' => 'heading',
            'language' => 'html',
            'content' => $text,
            'html' => '<' . $level . '>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</' . $level . '>',
        ];
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