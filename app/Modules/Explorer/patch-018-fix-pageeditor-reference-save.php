<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 018
 * Fix PageEditor Reference Save
 *
 * Ursache:
 * foreach ($pageData['children'] ?? [] as &$node)
 * arbeitet nicht zuverlässig auf der echten Array-Referenz.
 *
 * Fix:
 * - explizit prüfen, ob children existiert
 * - dann direkt über $pageData['children'] per Referenz iterieren
 * - API bleibt gleich
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 018 Fix PageEditor Reference Save gestartet');

    $write($root . '/app/Core/PageEditor.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class PageEditor
{
    public static function updateTextNodeContent(array &$pageData, string $nodeId, string $content): array
    {
        if (!isset($pageData['children']) || !is_array($pageData['children'])) {
            throw new RuntimeException("Page has no children array");
        }

        foreach ($pageData['children'] as &$node) {
            if (!is_array($node)) {
                continue;
            }

            $updatedNode = self::updateTextNodeRecursive($node, $nodeId, $content);

            if ($updatedNode !== null) {
                unset($node);
                return $updatedNode;
            }
        }

        unset($node);

        throw new RuntimeException("Node not found: {$nodeId}");
    }

    protected static function updateTextNodeRecursive(array &$node, string $nodeId, string $content): ?array
    {
        if (($node['id'] ?? '') === $nodeId) {
            if (($node['type'] ?? '') !== 'text') {
                throw new RuntimeException("Node is not editable as text: {$nodeId}");
            }

            $node['content'] = $content;
            $node['updated_at'] = date('c');

            return $node;
        }

        if (!isset($node['children']) || !is_array($node['children'])) {
            return null;
        }

        foreach ($node['children'] as &$child) {
            if (!is_array($child)) {
                continue;
            }

            $updatedNode = self::updateTextNodeRecursive($child, $nodeId, $content);

            if ($updatedNode !== null) {
                unset($child);
                return $updatedNode;
            }
        }

        unset($child);

        return null;
    }
}
PHP);

    $write($root . '/docs/fix-pageeditor-reference-save.md', <<<'MD'
# Fix PageEditor Reference Save

Patch 018 behebt ein Speicherproblem im PageEditor.

## Problem

Der bisherige Code verwendete:

```php
foreach ($pageData['children'] ?? [] as &$node)
```

Das kann dazu führen, dass Änderungen nicht zuverlässig im echten Page-Array landen.

## Lösung

Jetzt wird zuerst explizit geprüft:

```php
if (!isset($pageData['children']) || !is_array($pageData['children'])) {
    throw new RuntimeException("Page has no children array");
}
```

Danach wird direkt über das echte Array iteriert:

```php
foreach ($pageData['children'] as &$node)
```

## Test

```text
/explorer?workspace=draft
```

1. TextNode auswählen
2. Text ändern
3. In Draft speichern
4. Seite neu laden
5. Änderung muss erhalten bleiben
6. Datei prüfen:

```text
storage/workspaces/draft/pages/home.json
```

MD);

    $log('Patch 018 Fix PageEditor Reference Save fertig');
};
