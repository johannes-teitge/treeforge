<?php
declare(strict_types=1);

namespace TreeForge\Core;

use League\CommonMark\CommonMarkConverter;

class MarkdownRenderer
{
    protected static ?CommonMarkConverter $converter = null;

    public static function toHtml(string $markdown): string
    {
        return self::converter()->convert($markdown)->getContent();
    }

    protected static function converter(): CommonMarkConverter
    {
        if (self::$converter === null) {
            self::$converter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        return self::$converter;
    }
}