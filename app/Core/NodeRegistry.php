<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeRegistry
{
    /** @var array<string,class-string<Node>> */
    protected static array $nodes = [];

    /** @var array<string,string> */
    protected static array $aliases = [
        'textnode' => 'text',
        'text_node' => 'text',
        'richtext' => 'text',
        'richtextnode' => 'text',
        'rich_text_node' => 'text',

        'headingnode' => 'heading',
        'heading_node' => 'heading',
        'headline' => 'heading',
        'headlinenode' => 'heading',
        'title' => 'heading',
        'titlenode' => 'heading',

        'htmlnode' => 'html',
        'html_node' => 'html',
        'rawhtml' => 'html',
        'raw_html' => 'html',
        'rawhtmlnode' => 'html',
        'raw_html_node' => 'html',

        'javascriptnode' => 'javascript',
        'java_script_node' => 'javascript',
        'js' => 'javascript',
        'jsnode' => 'javascript',
        'js_node' => 'javascript',
        'script' => 'javascript',
        'scriptnode' => 'javascript',
        'script_node' => 'javascript',

        'imagenode' => 'image',
        'image_node' => 'image',
        'picture' => 'image',
        'picturenode' => 'image',

        'buttonnode' => 'button',
        'button_node' => 'button',

        'columnsnode' => 'columns',
        'columns_node' => 'columns',
        'columnsnodes' => 'columns',

        'columnnode' => 'column',
        'column_node' => 'column',

        'cssnode' => 'css',
        'css_node' => 'css',
        'stylenode' => 'css',
        'style_node' => 'css',

        'markdownnode' => 'markdown',
        'markdown_node' => 'markdown',
        'md' => 'markdown',
        'mdnode' => 'markdown',

        'codeblocknode' => 'codeblock',
        'code_block_node' => 'codeblock',
        'codeblock' => 'codeblock',
        'codehighlight' => 'codeblock',
        'codehighlighter' => 'codeblock',
        'codesnippet' => 'codeblock',
        'snippet' => 'codeblock',

        'pagemenunode' => 'pagemenu',
        'page_menu_node' => 'pagemenu',
        'pagemenu' => 'pagemenu',
        'page_menu' => 'pagemenu',
        'linkmenunode' => 'pagemenu',
        'link_menu_node' => 'pagemenu',
        'linkmenu' => 'pagemenu',
        'localmenu' => 'pagemenu',
        'anchor_menu' => 'pagemenu',

        'menuitemnode' => 'menuitem',
        'menu_item_node' => 'menuitem',
        'menuitem' => 'menuitem',
        'menu_item' => 'menuitem',
        'linkitem' => 'menuitem',
        'link_item_node' => 'menuitem',
    ];

    /**
     * @param class-string<Node> $class
     */
    public static function register(string $type, string $class): void
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Node class not found: {$class}");
        }

        if (!is_subclass_of($class, Node::class)) {
            throw new RuntimeException("Node class must extend Node: {$class}");
        }

        self::$nodes[$type] = $class;

        $normalized = self::normalizeKey($type);
        self::$nodes[$normalized] = $class;

        $canonical = self::$aliases[$normalized] ?? null;
        if ($canonical !== null && !isset(self::$nodes[$canonical])) {
            self::$nodes[$canonical] = $class;
        }
    }

    public static function resolve(string $type): ?string
    {
        if (isset(self::$nodes[$type])) {
            return self::$nodes[$type];
        }

        $normalized = self::normalizeKey($type);

        if (isset(self::$nodes[$normalized])) {
            return self::$nodes[$normalized];
        }

        $canonical = self::$aliases[$normalized] ?? null;
        if ($canonical !== null && isset(self::$nodes[$canonical])) {
            return self::$nodes[$canonical];
        }

        // Fallback: FooNode -> foo
        if (str_ends_with($normalized, 'node')) {
            $withoutNode = substr($normalized, 0, -4);
            if (isset(self::$nodes[$withoutNode])) {
                return self::$nodes[$withoutNode];
            }
        }

        return null;
    }

    public static function has(string $type): bool
    {
        return self::resolve($type) !== null;
    }

    public static function canonicalType(string $type): string
    {
        $normalized = self::normalizeKey($type);
        return self::$aliases[$normalized] ?? $normalized;
    }

    /**
     * @return array<string,class-string<Node>>
     */
    public static function all(): array
    {
        return self::$nodes;
    }

    protected static function normalizeKey(string $type): string
    {
        $type = trim($type);

        if (str_contains($type, '\\')) {
            $parts = explode('\\', $type);
            $type = (string)end($parts);
        }

        $type = strtolower($type);
        $type = str_replace(['-', ' '], '_', $type);
        $type = preg_replace('/[^a-z0-9_]/', '', $type) ?: 'unknown';

        return $type;
    }
}