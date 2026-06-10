<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 023
 * Tree Default Collapse
 *
 * - Startseite bleibt beim ersten Laden offen
 * - alle verschachtelten Nodes mit Kindern sind initial geschlossen
 * - vorhandener localStorage-Zustand wird respektiert
 * - Reset-Button leert Zustand und setzt Default erneut
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

    $log('Patch 023 Tree Default Collapse gestartet');

    $jsFile = $root . '/public/assets/js/explorer.js';

    if (!file_exists($jsFile)) {
        throw new RuntimeException('explorer.js nicht gefunden.');
    }

    $js = file_get_contents($jsFile);

    $old = <<<'JS_OLD'
  function initCollapsibleTree() {
    const storageKey = 'treeforge.explorer.collapsed';
    let collapsed = [];

    try {
      collapsed = JSON.parse(localStorage.getItem(storageKey) || '[]');
    } catch (error) {
      collapsed = [];
    }

    function saveState() {
      localStorage.setItem(storageKey, JSON.stringify(collapsed));
    }

    function setOpen(li, open) {
      if (!li) return;

      const id = li.getAttribute('data-tree-node-id') || 'page-root';
      const toggle = li.querySelector(':scope > .tf-tree-row > .tf-tree-toggle, :scope > .tf-tree-toggle');

      li.classList.toggle('is-open', open);
      li.classList.toggle('is-closed', !open);

      if (toggle) {
        toggle.textContent = open ? '▾' : '▸';
      }

      if (open) {
        collapsed = collapsed.filter((item) => item !== id);
      } else if (!collapsed.includes(id)) {
        collapsed.push(id);
      }

      saveState();
    }

    document.querySelectorAll('.tf-tree-page, .tf-tree-node.has-children').forEach((li) => {
      const id = li.getAttribute('data-tree-node-id') || 'page-root';
      setOpen(li, !collapsed.includes(id));
    });
JS_OLD;

    $new = <<<'JS_NEW'
  function initCollapsibleTree() {
    const storageKey = 'treeforge.explorer.collapsed';
    const initializedKey = 'treeforge.explorer.collapseInitialized';
    let collapsed = [];
    const hasStoredState = localStorage.getItem(initializedKey) === '1';

    try {
      collapsed = JSON.parse(localStorage.getItem(storageKey) || '[]');
    } catch (error) {
      collapsed = [];
    }

    if (!hasStoredState) {
      collapsed = [];

      document.querySelectorAll('.tf-tree-node.has-children').forEach((li) => {
        const id = li.getAttribute('data-tree-node-id');

        if (id) {
          collapsed.push(id);
        }
      });

      localStorage.setItem(initializedKey, '1');
      localStorage.setItem(storageKey, JSON.stringify(collapsed));
    }

    function saveState() {
      localStorage.setItem(initializedKey, '1');
      localStorage.setItem(storageKey, JSON.stringify(collapsed));
    }

    function setOpen(li, open) {
      if (!li) return;

      const id = li.getAttribute('data-tree-node-id') || 'page-root';
      const toggle = li.querySelector(':scope > .tf-tree-row > .tf-tree-toggle, :scope > .tf-tree-toggle');

      li.classList.toggle('is-open', open);
      li.classList.toggle('is-closed', !open);

      if (toggle) {
        toggle.textContent = open ? '▾' : '▸';
      }

      if (open) {
        collapsed = collapsed.filter((item) => item !== id);
      } else if (!collapsed.includes(id)) {
        collapsed.push(id);
      }

      saveState();
    }

    document.querySelectorAll('.tf-tree-page, .tf-tree-node.has-children').forEach((li) => {
      const id = li.getAttribute('data-tree-node-id') || 'page-root';

      if (id === 'page-root') {
        setOpen(li, true);
        return;
      }

      setOpen(li, !collapsed.includes(id));
    });
JS_NEW;

    if (str_contains($js, $old)) {
        $js = str_replace($old, $new, $js);
    } else {
        $log('Exakter initCollapsibleTree-Block nicht gefunden. Keine JS-Block-Ersetzung durchgeführt.');
    }

    $write($jsFile, $js);

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        $renderer = preg_replace(
            '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
            '<script src="/assets/js/explorer.js?v=023"></script>',
            $renderer
        );

        $write($rendererFile, $renderer);
    }

    $write($root . '/docs/tree-default-collapse.md', <<<'MD'
# Tree Default Collapse

Patch 023 ändert den Startzustand des Explorers.

## Neuer Default

Beim ersten Laden:

```text
▾ Startseite
   Text
   Image
   Button
   ▸ Columns
```

Die Startseite bleibt offen.

Alle verschachtelten Nodes mit Kindern sind zunächst geschlossen.

## Speicherung

Der Zustand wird weiterhin in `localStorage` gespeichert.

## Reset

Zum Zurücksetzen im Browser:

```js
localStorage.removeItem('treeforge.explorer.collapsed');
localStorage.removeItem('treeforge.explorer.collapseInitialized');
```

Danach Seite neu laden.

MD);

    $log('Patch 023 Tree Default Collapse fertig');
};
