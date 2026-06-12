<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 059
 * Fix Media Edit Preview URL
 *
 * Problem:
 * In /admin/media/edit.php wird das Bild nicht angezeigt.
 *
 * Ursache:
 * Die Edit-Seite nutzt eine andere Original-URL als das Media Grid.
 *
 * Fix:
 * Media Edit verwendet für Vorschau und Original-Link den stabilen Endpoint:
 * /api/media/file.php?path=...
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

    $log('Patch 059 Fix Media Edit Preview URL gestartet');

    $file = $root . '/public/admin/media/edit.php';

    if (!file_exists($file)) {
        throw new RuntimeException('public/admin/media/edit.php nicht gefunden.');
    }

    $content = file_get_contents($file);

    if (!str_contains($content, 'function mediaFileUrl(')) {
        $content = str_replace(
            "function csvToTags(string \$value): array\n{",
            "function mediaFileUrl(array \$item): string\n"
            . "{\n"
            . "    return '/api/media/file.php?' . http_build_query([\n"
            . "        'path' => (string)(\$item['relative_path'] ?? ''),\n"
            . "    ]);\n"
            . "}\n\n"
            . "function csvToTags(string \$value): array\n{",
            $content
        );
    }

    $content = preg_replace(
        '/\$url\s*=\s*\$repo->publicUrl\(\$item\);/',
        '$url = mediaFileUrl($item);',
        $content
    );

    $write($file, $content);

    $write($root . '/docs/treeforge/49-fix-media-edit-preview-url.md', <<<'MD'
# Fix Media Edit Preview URL

Patch 059 repariert die Bildvorschau in der Media-Edit-Seite.

## Problem

Im Grid wurden Bilder angezeigt, in der Edit-Seite aber nicht.

## Fix

Die Edit-Seite verwendet nun denselben stabilen Dateizugriff über:

```text
/api/media/file.php?path=...
```

Damit funktionieren auch Medien in Unterordnern wie:

```text
2026/06/datei.webp
```
MD);

    $log('Patch 059 Fix Media Edit Preview URL fertig');
};
