<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 062
 * Media Cache Busting
 *
 * Ziel:
 * - Ersetzte Bilder sollen ohne Strg+F5 sichtbar aktualisieren
 * - Media URLs bekommen einen v=filemtime Parameter
 * - Edit Preview nutzt denselben Cache-Buster
 * - Vorbereitung für spätere Render-Cache URLs
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

    $log('Patch 062 Media Cache Busting gestartet');

    $repoFile = $root . '/app/Modules/Media/MediaRepository.php';

    if (file_exists($repoFile)) {
        $repo = file_get_contents($repoFile);

        if (!str_contains($repo, 'publicUrlWithVersion')) {
            $repo = str_replace(
                "    public function publicUrl(array \$item): string\n    {\n        return \$this->config->publicOriginalUrl((string)(\$item['relative_path'] ?? ''));\n    }\n",
                "    public function publicUrl(array \$item): string\n    {\n        return \$this->config->publicOriginalUrl((string)(\$item['relative_path'] ?? ''));\n    }\n\n"
                . "    public function publicUrlWithVersion(array \$item): string\n"
                . "    {\n"
                . "        \$url = \$this->publicUrl(\$item);\n"
                . "        \$version = \$this->fileVersion(\$item);\n\n"
                . "        return \$url . (str_contains(\$url, '?') ? '&' : '?') . 'v=' . rawurlencode(\$version);\n"
                . "    }\n\n"
                . "    public function fileVersion(array \$item): string\n"
                . "    {\n"
                . "        \$relativePath = ltrim(str_replace('\\\\', '/', (string)(\$item['relative_path'] ?? '')), '/');\n"
                . "        \$file = rtrim(\$this->config->originalsDir(), '/\\\\') . '/' . \$relativePath;\n\n"
                . "        if (is_file(\$file)) {\n"
                . "            return (string)filemtime(\$file);\n"
                . "        }\n\n"
                . "        return (string)(\$item['updated_at'] ?? \$item['replaced_at'] ?? \$item['uploaded_at'] ?? time());\n"
                . "    }\n",
                $repo
            );

            $write($repoFile, $repo);
        }
    }

    $mediaIndex = $root . '/public/admin/media/index.php';

    if (file_exists($mediaIndex)) {
        $content = file_get_contents($mediaIndex);

        if (!str_contains($content, 'publicUrlWithVersion')) {
            $content = str_replace(
                'publicUrl($item)',
                'publicUrlWithVersion($item)',
                $content
            );
        }

        $write($mediaIndex, $content);
    }

    $editFile = $root . '/public/admin/media/edit.php';

    if (file_exists($editFile)) {
        $edit = file_get_contents($editFile);

        $edit = str_replace(
            '$url = mediaFileUrl($item);',
            '$url = $repo->publicUrl($item);' . "\n" . '$previewUrl = $repo->publicUrlWithVersion($item);',
            $edit
        );

        $edit = str_replace(
            '$previewUrl = $url . \'&v=\' . rawurlencode((string)($item[\'updated_at\'] ?? time()));',
            '$previewUrl = $repo->publicUrlWithVersion($item);',
            $edit
        );

        $edit = str_replace(
            '<img src="\' . e($url) . \'',
            '<img src="\' . e($previewUrl ?? $url) . \'',
            $edit
        );

        $edit = str_replace(
            '<img src="\' . e($previewUrl ?? $url) . \'"',
            '<img src="\' . e($previewUrl ?? $url) . \'"',
            $edit
        );

        $write($editFile, $edit);
    }

    $write($root . '/docs/treeforge/52-media-cache-busting.md', <<<'MD'
# Media Cache Busting

Patch 062 ergänzt Cache Busting für Media URLs.

## Problem

Nach dem Ersetzen eines Bildes war die neue Datei erst nach Strg+F5 sichtbar.

## Lösung

Media URLs bekommen automatisch einen Versionsparameter:

```text
/api/media/file.php?path=2026/06/bild.webp&v=1780000000
```

Der Wert basiert primär auf:

```text
filemtime(original)
```

Wenn die Datei ersetzt wird, ändert sich `filemtime`, damit ändert sich die URL und der Browser lädt neu.

## Betroffene Bereiche

- Media Grid
- Media Edit Preview
- Original-Link bleibt ohne Cache-Buster, sofern direkt geöffnet
MD);

    $log('Patch 062 Media Cache Busting fertig');
};
