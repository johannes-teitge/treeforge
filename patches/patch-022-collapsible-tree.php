<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 022
 * Collapsible Tree
 *
 * - Nodes mit Kindern erhalten Toggle-Button
 * - Auf-/Zuklappen im Explorer
 * - Buttons "Alle aufklappen" / "Alle zuklappen"
 * - Zustand wird in localStorage gespeichert
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

    $log('Patch 022 Collapsible Tree gestartet');

    $write($root . '/app/Modules/Explorer/ExplorerTree.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Explorer;

use TreeForge\Core\NodeInspector;

class ExplorerTree
{
    public function renderPageTree(array $pageData): string
    {
        $title = htmlspecialchars((string)($pageData['title'] ?? $pageData['id'] ?? 'Page'), ENT_QUOTES, 'UTF-8');

        $html = '<ul class="tf-explorer-tree">';
        $html .= '<li class="tf-tree-page is-open">';
        $html .= '<div class="tf-tree-row">';
        $html .= '<button class="tf-tree-toggle" type="button" aria-label="Toggle page">▾</button>';
        $html .= '<span class="tf-tree-label">🌳 ' . $title . '</span>';
        $html .= '</div>';

        $children = $pageData['children'] ?? [];

        if (is_array($children) && $children !== []) {
            $html .= '<ul class="tf-tree-children">';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= $this->renderNode($child);
                }
            }
            $html .= '</ul>';
        }

        $html .= '</li></ul>';

        return $html;
    }

    protected function renderNode(array $node): string
    {
        $id = htmlspecialchars((string)($node['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type = (string)($node['type'] ?? 'unknown');
        $icon = NodeInspector::typeIcon($type);
        $label = htmlspecialchars(NodeInspector::typeLabel($type), ENT_QUOTES, 'UTF-8');
        $inspect = NodeInspector::inspectArray($node);

        $json = htmlspecialchars(
            json_encode($inspect, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            ENT_QUOTES,
            'UTF-8'
        );

        $children = $node['children'] ?? [];
        $hasChildren = is_array($children) && $children !== [];

        $html = '<li class="tf-tree-node' . ($hasChildren ? ' has-children is-open' : '') . '" data-tree-node-id="' . $id . '">';

        if ($hasChildren) {
            $html .= '<div class="tf-tree-row">';
            $html .= '<button class="tf-tree-toggle" type="button" aria-label="Toggle node">▾</button>';
        }

        $html .= '<button class="tf-tree-node-button" type="button" data-node-json="' . $json . '">';
        $html .= '<span class="tf-node-main">' . $icon . ' ' . $label . '</span>';
        $html .= '<span class="tf-node-id">' . $id . '</span>';
        $html .= '</button>';

        if ($hasChildren) {
            $html .= '</div>';
            $html .= '<ul class="tf-tree-children">';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= $this->renderNode($child);
                }
            }
            $html .= '</ul>';
        }

        $html .= '</li>';

        return $html;
    }
}
PHP);

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        $renderer = str_replace(
            '{$tree}',
            '<div class="tf-tree-toolbar"><button type="button" class="tf-tree-tool" id="tfExpandAll">Alle aufklappen</button><button type="button" class="tf-tree-tool" id="tfCollapseAll">Alle zuklappen</button></div>' . "\n\n      " . '{$tree}',
            $renderer
        );

        $renderer = preg_replace(
            '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
            '<script src="/assets/js/explorer.js?v=022"></script>',
            $renderer
        );

        $write($rendererFile, $renderer);
    }

    $jsFile = $root . '/public/assets/js/explorer.js';

    if (file_exists($jsFile)) {
        $js = file_get_contents($jsFile);

        if (!str_contains($js, 'function initCollapsibleTree')) {
            $insert = <<<'JS_APPEND'

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

    document.querySelectorAll('.tf-tree-toggle').forEach((toggle) => {
      toggle.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        const li = toggle.closest('.tf-tree-page, .tf-tree-node.has-children');
        const isOpen = li && li.classList.contains('is-open');

        setOpen(li, !isOpen);
      });
    });

    const expandAll = document.getElementById('tfExpandAll');
    const collapseAll = document.getElementById('tfCollapseAll');

    if (expandAll) {
      expandAll.addEventListener('click', () => {
        collapsed = [];
        document.querySelectorAll('.tf-tree-page, .tf-tree-node.has-children').forEach((li) => setOpen(li, true));
        saveState();
      });
    }

    if (collapseAll) {
      collapseAll.addEventListener('click', () => {
        collapsed = [];
        document.querySelectorAll('.tf-tree-node.has-children').forEach((li) => setOpen(li, false));
        saveState();
      });
    }
  }

  initCollapsibleTree();
JS_APPEND;

            $js = str_replace(
                "})();",
                $insert . "\n})();",
                $js
            );

            $write($jsFile, $js);
        }
    }

    $cssFile = $root . '/public/assets/css/explorer.css';

    if (file_exists($cssFile)) {
        $css = file_get_contents($cssFile);

        if (!str_contains($css, '.tf-tree-toolbar')) {
            $css .= <<<'CSS'

.tf-tree-toolbar {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
  margin: 0 0 .85rem;
}

.tf-tree-tool {
  border: 1px solid rgba(30, 61, 28, .18);
  background: #fff;
  color: var(--tf-green);
  border-radius: .7rem;
  padding: .45rem .7rem;
  font-weight: 800;
  cursor: pointer;
}

.tf-tree-row {
  display: flex;
  align-items: stretch;
  gap: .35rem;
  width: 100%;
}

.tf-tree-toggle {
  flex: 0 0 2rem;
  border: 1px solid rgba(30, 61, 28, .12);
  background: #fff;
  color: var(--tf-green);
  border-radius: .65rem;
  cursor: pointer;
  font-weight: 900;
}

.tf-tree-page > .tf-tree-row {
  align-items: center;
  margin-bottom: .65rem;
}

.tf-tree-page > .tf-tree-row .tf-tree-label {
  margin-bottom: 0;
}

.tf-tree-node.has-children > .tf-tree-row > .tf-tree-node-button {
  flex: 1 1 auto;
}

.tf-tree-node.is-closed > .tf-tree-children,
.tf-tree-page.is-closed > .tf-tree-children {
  display: none;
}

.tf-tree-node.is-closed > .tf-tree-row > .tf-tree-node-button,
.tf-tree-page.is-closed > .tf-tree-row {
  opacity: .95;
}
CSS;

            $write($cssFile, $css);
        }
    }

    $write($root . '/docs/collapsible-tree.md', <<<'MD'
# Collapsible Tree

Patch 022 ergänzt Auf- und Zuklappen im Explorer.

## Funktionen

- Nodes mit Kindern bekommen einen Toggle-Pfeil.
- `▾` bedeutet geöffnet.
- `▸` bedeutet geschlossen.
- Alle aufklappen.
- Alle zuklappen.
- Zustand wird in `localStorage` gespeichert.

## Warum wichtig?

Bei verschachtelten Strukturen wie Columns wird der Explorer sonst schnell unübersichtlich.

```text
Columns
├─ Column
│  ├─ Text
│  └─ Image
└─ Column
   ├─ Text
   └─ Button
```

Zugeklappt:

```text
▸ Columns
```

MD);

    $log('Patch 022 Collapsible Tree fertig');
};
