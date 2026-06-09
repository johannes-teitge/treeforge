<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeRegistry
{
    protected static array $nodes = [];

    public static function register(string $type, string $class): void
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Node class not found: {$class}");
        }

        if (!is_subclass_of($class, Node::class)) {
            throw new RuntimeException("Node class must extend Node: {$class}");
        }

        self::$nodes[$type] = $class;
    }

    public static function resolve(string $type): ?string
    {
        return self::$nodes[$type] ?? null;
    }

    public static function has(string $type): bool
    {
        return isset(self::$nodes[$type]);
    }

    public static function all(): array
    {
        return self::$nodes;
    }
}