<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 060
 * Fix Media Edit Preview Cache Parameter
 *
 * Problem:
 * Die Edit-Vorschau hängt ?v=... an eine URL, die bereits ?path=... enthält.
 * Dadurch wird der Pfad ungültig:
 *
 * /api/media/file.php?path=2026/06/bild.webp?v=...
 *
 * Fix:
 * Preview-URL wird mit &v=... ergänzt.
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

    $log('Patch 060 Fix Media Edit Preview Cache Parameter gestartet');

    $file = $root . '/public/admin/media/edit.php';

    if (!file_exists($file)) {
        throw new RuntimeException('public/admin/media/edit.php nicht gefunden.');
    }

    $content = file_get_contents($file);

    if (!str_contains($content, '$previewUrl =')) {
        $content = str_replace(
            '$tags = implode(\', \', (array)($item[\'tags\'] ?? []));',
            '$previewUrl = $url . \'&v=\' . rawurlencode((string)($item[\'updated_at\'] ?? time()));' . "\n" .
            '$tags = implode(\', \', (array)($item[\'tags\'] ?? []));',
            $content
        );
    }

    $content = str_replace(
        '?v=\' . e((string)($item[\'updated_at\'] ?? time())) . \'',
        '',
        $content
    );

    $content = str_replace(
        '<img src="\' . e($url) . \'"',
        '<img src="\' . e($previewUrl ?? $url) . \'"',
        $content
    );

    $write($file, $content);

    $write($root . '/docs/treeforge/50-fix-media-edit-preview-cache-parameter.md', <<<'MD'
# Fix Media Edit Preview Cache Parameter

Patch 060 repariert den Cache-Buster der Media-Edit-Vorschau.

## Problem

Die URL war sinngemäß:

```text
/api/media/file.php?path=2026/06/bild.webp?v=...
```

Der Cache-Parameter landete dadurch im `path`.

## Fix

Jetzt:

```text
/api/media/file.php?path=2026/06/bild.webp&v=...
```
MD);

    $log('Patch 060 Fix Media Edit Preview Cache Parameter fertig');
};
