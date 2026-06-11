<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 029
 * Fix Node Wizard Init Timing
 *
 * Ursache:
 * Der Wizard-HTML-Block steht aktuell nach dem explorer.js Script.
 * Dadurch läuft initNodeWizard(), bevor #tfNodeWizard im DOM existiert.
 *
 * Fix:
 * - initNodeWizard wird erst nach DOMContentLoaded ausgeführt
 * - falls DOM schon geladen ist, sofort ausführen
 * - Cache-Buster auf v029
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

    $log('Patch 029 Fix Node Wizard Init Timing gestartet');

    $jsFile = $root . '/public/assets/js/explorer.js';

    if (!file_exists($jsFile)) {
        throw new RuntimeException('explorer.js nicht gefunden.');
    }

    $js = file_get_contents($jsFile);

    $js = str_replace(
        "  initNodeWizard();",
        "  if (document.readyState === 'loading') {\n    document.addEventListener('DOMContentLoaded', initNodeWizard);\n  } else {\n    initNodeWizard();\n  }",
        $js
    );

    $write($jsFile, $js);

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        $renderer = preg_replace(
            '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
            '<script src="/assets/js/explorer.js?v=029"></script>',
            $renderer
        );

        $write($rendererFile, $renderer);
    }

    $write($root . '/docs/fix-node-wizard-init-timing.md', <<<'MD'
# Fix Node Wizard Init Timing

Patch 029 behebt, dass `+ Node` keine Reaktion zeigt.

## Ursache

Der Wizard-HTML-Block wurde nach dem Script eingefügt:

```html
<script src="/assets/js/explorer.js?v=028"></script>

<div id="tfNodeWizard">...</div>
```

Dadurch konnte `initNodeWizard()` das Modal noch nicht finden.

## Lösung

`initNodeWizard()` läuft jetzt erst nach `DOMContentLoaded`.

## Test

```text
/explorer?workspace=draft
```

Danach hart neu laden:

```text
Strg + F5
```

Dann auf `+ Node` klicken.

MD);

    $log('Patch 029 Fix Node Wizard Init Timing fertig');
};
