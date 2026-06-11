<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 040
 * Fix Page Settings CSS Loading
 *
 * Problem:
 * - page-settings.css wurde per @import am Ende von admin.css eingefügt.
 * - CSS @import ist dort ungültig/unsicher und kann vom Browser ignoriert werden.
 * - Dadurch erscheinen die Page-Settings ungestylt.
 *
 * Fix:
 * - AdminLayout lädt /assets/css/page-settings.css direkt im <head>
 * - ungültiger @import wird aus admin.css entfernt
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

    $log('Patch 040 Fix Page Settings CSS Loading gestartet');

    $layoutFile = $root . '/app/Admin/AdminLayout.php';

    if (file_exists($layoutFile)) {
        $layout = file_get_contents($layoutFile);

        $old = <<<'PHP'
            . '<link rel="stylesheet" href="/assets/css/admin.css">'
            . '</head>'
PHP;

        $new = <<<'PHP'
            . '<link rel="stylesheet" href="/assets/css/admin.css">'
            . '<link rel="stylesheet" href="/assets/css/page-settings.css">'
            . '</head>'
PHP;

        if (str_contains($layout, $old) && !str_contains($layout, 'page-settings.css')) {
            $layout = str_replace($old, $new, $layout);
            $write($layoutFile, $layout);
        } else {
            $log('AdminLayout enthält page-settings.css bereits oder Zielblock wurde nicht gefunden');
        }
    }

    $adminCssFile = $root . '/public/assets/css/admin.css';

    if (file_exists($adminCssFile)) {
        $css = file_get_contents($adminCssFile);

        $css = str_replace(
            "\n@import url('/assets/css/page-settings.css');\n",
            "\n",
            $css
        );

        $css = str_replace(
            "@import url('/assets/css/page-settings.css');\n",
            "",
            $css
        );

        $write($adminCssFile, $css);
    }

    $write($root . '/docs/treeforge/30-fix-page-settings-css-loading.md', <<<'MD'
# Fix Page Settings CSS Loading

Patch 040 behebt das Styling der Page-Settings.

## Ursache

`page-settings.css` wurde per `@import` am Ende von `admin.css` eingebunden.

CSS-Imports müssen am Anfang einer CSS-Datei stehen und können sonst vom Browser ignoriert werden.

## Fix

`AdminLayout.php` lädt nun beide Stylesheets direkt:

```html
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/assets/css/page-settings.css">
```

Der alte `@import` wurde aus `admin.css` entfernt.
MD);

    $log('Patch 040 Fix Page Settings CSS Loading fertig');
};
