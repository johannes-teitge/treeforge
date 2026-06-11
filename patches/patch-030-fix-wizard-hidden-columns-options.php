<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 030
 * Fix Wizard Hidden Columns Options
 *
 * Problem:
 * Das Columns-Options-Feld bleibt sichtbar, obwohl hidden gesetzt wird.
 *
 * Fix:
 * - CSS-Regel für [hidden] im Wizard
 * - JS setzt zusätzlich style.display
 * - Cache-Buster auf v030
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

    $log('Patch 030 Fix Wizard Hidden Columns Options gestartet');

    $cssFile = $root . '/public/assets/css/explorer.css';

    if (file_exists($cssFile)) {
        $css = file_get_contents($cssFile);

        if (!str_contains($css, '.tf-form-row[hidden]')) {
            $css .= <<<'CSS'

.tf-form-row[hidden],
.tf-modal [hidden] {
  display: none !important;
}
CSS;

            $write($cssFile, $css);
        }
    }

    $jsFile = $root . '/public/assets/js/explorer.js';

    if (file_exists($jsFile)) {
        $js = file_get_contents($jsFile);

        $old = <<<'JS'
    function updateTypeUi() {
      columnsOptions.hidden = typeSelect.value !== 'columns';
    }
JS;

        $new = <<<'JS'
    function updateTypeUi() {
      const isColumns = typeSelect.value === 'columns';
      columnsOptions.hidden = !isColumns;
      columnsOptions.style.display = isColumns ? 'grid' : 'none';
    }
JS;

        if (str_contains($js, $old)) {
            $js = str_replace($old, $new, $js);
            $write($jsFile, $js);
        }
    }

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        $renderer = preg_replace(
            '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
            '<script src="/assets/js/explorer.js?v=030"></script>',
            $renderer
        );

        $write($rendererFile, $renderer);
    }

    $write($root . '/docs/fix-wizard-hidden-columns-options.md', <<<'MD'
# Fix Wizard Hidden Columns Options

Patch 030 behebt, dass die Columns-Optionen bei jedem Node-Typ sichtbar waren.

## Ursache

Die Klasse `.tf-form-row` setzt `display: grid`.

Dadurch konnte das `hidden`-Attribut optisch übersteuert werden.

## Fix

```css
.tf-form-row[hidden],
.tf-modal [hidden] {
  display: none !important;
}
```

Zusätzlich setzt JS nun explizit:

```js
columnsOptions.style.display = isColumns ? 'grid' : 'none';
```

MD);

    $log('Patch 030 Fix Wizard Hidden Columns Options fertig');
};
