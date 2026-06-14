<?php
declare(strict_types=1);

namespace TreeForge\Core;

/**
 * Zentrale Node-ID-Hilfe.
 *
 * Interne Node-IDs bleiben stabil und kurz:
 *   n_4f91a7c02e3bd8aa
 *
 * Die DOM-ID wird daraus separat abgeleitet:
 *   tf-n-4f91a7c02e3bd8aa
 */
final class NodeIdGenerator
{
    public const PREFIX = 'n_';
    public const RANDOM_BYTES = 8; // 16 Hex-Zeichen

    /**
     * Erzeugt eine neue ID und prüft gegen bereits verwendete IDs.
     * Die neue ID wird direkt in $usedIds eingetragen.
     *
     * @param array<int|string,string> $usedIds
     */
    public static function generateFromIds(array &$usedIds): string
    {
        $lookup = [];
        foreach ($usedIds as $id) {
            $id = (string)$id;
            if ($id !== '') {
                $lookup[$id] = true;
            }
        }

        do {
            $id = self::PREFIX . bin2hex(random_bytes(self::RANDOM_BYTES));
        } while (isset($lookup[$id]));

        $usedIds[] = $id;
        return $id;
    }

    /**
     * Erzeugt eine neue ID gegen einen vorhandenen Baum.
     */
    public static function generateForTree(array $tree): string
    {
        $usedIds = self::collectIds($tree);
        return self::generateFromIds($usedIds);
    }

    /**
     * Sammelt alle IDs aus einem Node-/Page-Baum.
     *
     * @return array<int,string>
     */
    public static function collectIds(array $node): array
    {
        $ids = [];
        self::collectIdsRecursive($node, $ids);
        return array_values(array_unique(array_filter($ids, static fn(string $id): bool => $id !== '')));
    }

    /**
     * @param array<int,string> $ids
     */
    private static function collectIdsRecursive(array $node, array &$ids): void
    {
        $id = (string)($node['id'] ?? '');
        if ($id !== '') {
            $ids[] = $id;
        }

        foreach ((array)($node['children'] ?? []) as $child) {
            if (is_array($child)) {
                self::collectIdsRecursive($child, $ids);
            }
        }
    }

    public static function isModernNodeId(string $id): bool
    {
        return preg_match('/^n_[a-f0-9]{16}$/', $id) === 1;
    }

    /**
     * Interne Node-ID in sichere DOM-ID umwandeln.
     */
    public static function toDomId(string $nodeId): string
    {
        $nodeId = trim($nodeId);
        if ($nodeId === '') {
            return 'tf-node-unknown';
        }

        $dom = preg_replace('/[^A-Za-z0-9_-]+/', '-', $nodeId) ?: 'node';
        $dom = trim($dom, '-_');

        if ($dom === '') {
            $dom = 'node';
        }

        return 'tf-' . str_replace('_', '-', $dom);
    }
}