<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 027
 * Node Creation Wizard
 *
 * - + Node Button im Explorer
 * - Modal/Wizard für Text, Image, Button, Markdown, CSS, Columns
 * - Columns Wizard mit 2-6 Spalten
 * - API: POST /api/node/create.php
 * - speichert ausschließlich im Draft Workspace
 * - ausgewählte Node wird als Parent verwendet, sonst Root/Page
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

    $log('Patch 027 Node Creation Wizard gestartet');

    $write($root . '/app/Core/NodeCreator.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeCreator
{
    public const ALLOWED_TYPES = [
        'text',
        'image',
        'button',
        'markdown',
        'css',
        'columns',
    ];

    public static function createNode(string $type, array $options = []): array
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new RuntimeException("Unsupported node type: {$type}");
        }

        $id = self::id('node_' . $type);

        return match ($type) {
            'text' => [
                'id' => $id,
                'type' => 'text',
                'content' => "Neuer Text",
            ],

            'image' => [
                'id' => $id,
                'type' => 'image',
                'src' => '/assets/img/treeforge-demo.svg',
                'alt' => 'Neues Bild',
                'caption' => '',
            ],

            'button' => [
                'id' => $id,
                'type' => 'button',
                'label' => 'Neuer Button',
                'url' => '#',
                'variant' => 'primary',
            ],

            'markdown' => [
                'id' => $id,
                'type' => 'markdown',
                'content' => "# Neue Markdown Node\n\nHier kann **Markdown** geschrieben werden.",
            ],

            'css' => [
                'id' => $id,
                'type' => 'css',
                'content' => ".tf-custom {\n  color: #1E3D1C;\n}",
            ],

            'columns' => self::createColumnsNode($id, $options),

            default => throw new RuntimeException("Unsupported node type: {$type}"),
        };
    }

    public static function appendNode(array &$pageData, ?string $parentId, array $node): void
    {
        if ($parentId === null || $parentId === '' || $parentId === 'root' || $parentId === 'page') {
            if (!isset($pageData['children']) || !is_array($pageData['children'])) {
                $pageData['children'] = [];
            }

            $pageData['children'][] = $node;
            return;
        }

        if (!isset($pageData['children']) || !is_array($pageData['children'])) {
            throw new RuntimeException('Page has no children array');
        }

        foreach ($pageData['children'] as $index => $child) {
            if (!is_array($child)) {
                continue;
            }

            if (self::appendNodeRecursive($pageData['children'][$index], $parentId, $node)) {
                return;
            }
        }

        throw new RuntimeException("Parent node not found: {$parentId}");
    }

    protected static function appendNodeRecursive(array &$currentNode, string $parentId, array $node): bool
    {
        if ((string)($currentNode['id'] ?? '') === $parentId) {
            if (!isset($currentNode['children']) || !is_array($currentNode['children'])) {
                $currentNode['children'] = [];
            }

            $currentNode['children'][] = $node;
            return true;
        }

        if (!isset($currentNode['children']) || !is_array($currentNode['children'])) {
            return false;
        }

        foreach ($currentNode['children'] as $index => $child) {
            if (!is_array($child)) {
                continue;
            }

            if (self::appendNodeRecursive($currentNode['children'][$index], $parentId, $node)) {
                return true;
            }
        }

        return false;
    }

    protected static function createColumnsNode(string $id, array $options): array
    {
        $columns = (int)($options['columns'] ?? 2);
        $columns = max(2, min(6, $columns));

        $gap = trim((string)($options['gap'] ?? '1rem'));
        $gap = $gap !== '' ? $gap : '1rem';

        $children = [];

        for ($i = 1; $i <= $columns; $i++) {
            $children[] = [
                'id' => self::id('node_column_' . $i),
                'type' => 'column',
                'width' => '1fr',
                'children' => [],
            ];
        }

        return [
            'id' => $id,
            'type' => 'columns',
            'settings' => [
                'columns' => $columns,
                'gap' => $gap,
            ],
            'children' => $children,
        ];
    }

    protected static function id(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(4));
    }
}
PHP);

    $write($root . '/public/api/node/create.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Core\NodeCreator;
use TreeForge\Core\NodeInspector;
use TreeForge\Core\Workspace;

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Only POST allowed');
    }

    $root = dirname(__DIR__, 3);
    $payload = json_decode((string)file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        throw new RuntimeException('Invalid JSON payload');
    }

    $pageId = (string)($payload['page'] ?? 'home');
    $parentId = (string)($payload['parent'] ?? '');
    $type = (string)($payload['type'] ?? '');
    $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];

    if ($type === '') {
        throw new RuntimeException('Missing node type');
    }

    $workspace = Workspace::draft($root);
    $workspace->ensurePage($pageId);

    $pageData = $workspace->loadPageArray($pageId);
    $newNode = NodeCreator::createNode($type, $options);

    NodeCreator::appendNode($pageData, $parentId, $newNode);

    $pageData['_workflow'] = [
        'status' => 'draft_changed',
        'action' => 'node_created',
        'created_at' => date('c'),
    ];

    $workspace->savePage($pageId, $pageData);

    echo json_encode([
        'ok' => true,
        'message' => 'Node wurde im Draft angelegt.',
        'workspace' => 'draft',
        'page' => $pageId,
        'parent' => $parentId,
        'node' => $newNode['id'],
        'type' => $type,
        'inspector' => NodeInspector::inspectArray($newNode),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
PHP);

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        if (!str_contains($renderer, 'id="tfAddNode"')) {
            $renderer = str_replace(
                '<div class="tf-tree-toolbar"><button type="button" class="tf-tree-tool" id="tfExpandAll">Alle aufklappen</button><button type="button" class="tf-tree-tool" id="tfCollapseAll">Alle zuklappen</button></div>',
                '<div class="tf-tree-toolbar"><button type="button" class="tf-tree-tool primary" id="tfAddNode">+ Node</button><button type="button" class="tf-tree-tool" id="tfExpandAll">Alle aufklappen</button><button type="button" class="tf-tree-tool" id="tfCollapseAll">Alle zuklappen</button></div>',
                $renderer
            );
        }

        if (!str_contains($renderer, 'id="tfNodeWizard"')) {
            $modal = <<<'HTML'

  <div class="tf-modal-backdrop" id="tfNodeWizard" hidden>
    <div class="tf-modal" role="dialog" aria-modal="true" aria-labelledby="tfNodeWizardTitle">
      <div class="tf-modal-head">
        <h2 id="tfNodeWizardTitle">Node hinzufügen</h2>
        <button type="button" class="tf-modal-close" id="tfNodeWizardClose" aria-label="Schließen">×</button>
      </div>

      <div class="tf-modal-body">
        <div class="tf-form-row">
          <label for="tfNodeType">Node Typ</label>
          <select id="tfNodeType" class="tf-input">
            <option value="text">Text</option>
            <option value="image">Image</option>
            <option value="button">Button</option>
            <option value="markdown">Markdown</option>
            <option value="css">CSS</option>
            <option value="columns">Columns</option>
          </select>
        </div>

        <div class="tf-form-row" id="tfColumnsOptions" hidden>
          <label>Spaltenanzahl</label>
          <div class="tf-segmented">
            <label><input type="radio" name="tfColumnsCount" value="2" checked><span>2</span></label>
            <label><input type="radio" name="tfColumnsCount" value="3"><span>3</span></label>
            <label><input type="radio" name="tfColumnsCount" value="4"><span>4</span></label>
            <label><input type="radio" name="tfColumnsCount" value="5"><span>5</span></label>
            <label><input type="radio" name="tfColumnsCount" value="6"><span>6</span></label>
          </div>

          <label for="tfColumnsGap" class="mt-small">Gap</label>
          <input id="tfColumnsGap" class="tf-input" value="1rem">
        </div>

        <div class="tf-wizard-info" id="tfNodeWizardInfo">
          Neue Node wird am Ende der Startseite angelegt.
        </div>
      </div>

      <div class="tf-modal-actions">
        <button type="button" class="tf-action-button secondary" id="tfNodeWizardCancel">Abbrechen</button>
        <button type="button" class="tf-action-button" id="tfNodeWizardCreate">Anlegen</button>
      </div>
    </div>
  </div>
HTML;

            $renderer = str_replace(
                '</body>',
                $modal . "\n</body>",
                $renderer
            );
        }

        $renderer = preg_replace(
            '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
            '<script src="/assets/js/explorer.js?v=027"></script>',
            $renderer
        );

        $write($rendererFile, $renderer);
    }

    $jsFile = $root . '/public/assets/js/explorer.js';

    if (file_exists($jsFile)) {
        $js = file_get_contents($jsFile);

        if (!str_contains($js, 'function initNodeWizard')) {
            $wizardJs = <<<'JS_APPEND'

  function initNodeWizard() {
    const addButton = document.getElementById('tfAddNode');
    const modal = document.getElementById('tfNodeWizard');
    const closeButton = document.getElementById('tfNodeWizardClose');
    const cancelButton = document.getElementById('tfNodeWizardCancel');
    const createButton = document.getElementById('tfNodeWizardCreate');
    const typeSelect = document.getElementById('tfNodeType');
    const columnsOptions = document.getElementById('tfColumnsOptions');
    const columnsGap = document.getElementById('tfColumnsGap');
    const info = document.getElementById('tfNodeWizardInfo');

    if (!addButton || !modal || !createButton || !typeSelect) {
      return;
    }

    function selectedParentId() {
      if (!selectedNode || !selectedNode.id || selectedNode.id === '–') {
        return '';
      }

      return selectedNode.id;
    }

    function updateInfo() {
      const parent = selectedParentId();

      if (parent) {
        info.textContent = 'Neue Node wird als Child von "' + parent + '" angelegt.';
      } else {
        info.textContent = 'Neue Node wird am Ende der Startseite angelegt.';
      }
    }

    function updateTypeUi() {
      columnsOptions.hidden = typeSelect.value !== 'columns';
    }

    function openModal() {
      updateInfo();
      updateTypeUi();
      modal.hidden = false;
      typeSelect.focus();
    }

    function closeModal() {
      modal.hidden = true;
    }

    addButton.addEventListener('click', openModal);
    closeButton && closeButton.addEventListener('click', closeModal);
    cancelButton && cancelButton.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });

    typeSelect.addEventListener('change', updateTypeUi);

    createButton.addEventListener('click', async () => {
      const type = typeSelect.value;
      const options = {};

      if (type === 'columns') {
        const count = document.querySelector('input[name="tfColumnsCount"]:checked');
        options.columns = count ? parseInt(count.value, 10) : 2;
        options.gap = columnsGap ? columnsGap.value : '1rem';
      }

      const oldText = createButton.textContent;
      createButton.disabled = true;
      createButton.textContent = 'Lege an ...';

      try {
        const response = await fetch('/api/node/create.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            page: (window.TreeForgeExplorer && window.TreeForgeExplorer.page) || 'home',
            parent: selectedParentId(),
            type: type,
            options: options
          })
        });

        const raw = await response.text();
        let result;

        try {
          result = JSON.parse(raw);
        } catch (parseError) {
          throw new Error('API liefert kein JSON: ' + raw.substring(0, 180));
        }

        if (!response.ok || !result.ok) {
          throw new Error(result.error || 'Node konnte nicht angelegt werden');
        }

        showNotice('success', result.message || 'Node angelegt.');
        closeModal();

        setTimeout(() => {
          window.location.href = '/explorer?workspace=draft';
        }, 450);

      } catch (error) {
        showNotice('error', error.message);
      } finally {
        createButton.disabled = false;
        createButton.textContent = oldText;
      }
    });
  }

  initNodeWizard();
JS_APPEND;

            $js = str_replace(
                "})();",
                $wizardJs . "\n})();",
                $js
            );

            $write($jsFile, $js);
        }
    }

    $cssFile = $root . '/public/assets/css/explorer.css';

    if (file_exists($cssFile)) {
        $css = file_get_contents($cssFile);

        if (!str_contains($css, '.tf-modal-backdrop')) {
            $css .= <<<'CSS'

.tf-tree-tool.primary {
  background: var(--tf-green);
  color: #fff;
  border-color: var(--tf-green);
}

.tf-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(18, 26, 23, .42);
  z-index: 9998;
  display: grid;
  place-items: center;
  padding: 1rem;
}

.tf-modal-backdrop[hidden] {
  display: none;
}

.tf-modal {
  width: min(560px, 100%);
  background: var(--tf-cream);
  border: 1px solid rgba(23, 63, 53, .16);
  border-radius: 1.2rem;
  box-shadow: 0 2rem 5rem rgba(0, 0, 0, .24);
  overflow: hidden;
}

.tf-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.15rem;
  border-bottom: 1px solid var(--tf-border);
}

.tf-modal-head h2 {
  margin: 0;
  color: var(--tf-green);
  font-size: 1.1rem;
}

.tf-modal-close {
  border: 0;
  background: #fff;
  color: var(--tf-green);
  border-radius: .7rem;
  width: 2.2rem;
  height: 2.2rem;
  font-size: 1.4rem;
  cursor: pointer;
}

.tf-modal-body {
  padding: 1.15rem;
}

.tf-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: .75rem;
  padding: 1rem 1.15rem;
  border-top: 1px solid var(--tf-border);
}

.tf-form-row {
  display: grid;
  gap: .45rem;
  margin-bottom: 1rem;
}

.tf-form-row label {
  font-weight: 800;
  color: var(--tf-green);
}

.tf-input {
  width: 100%;
  border: 1px solid rgba(23, 63, 53, .22);
  background: #fff;
  color: var(--tf-dark);
  border-radius: .8rem;
  padding: .7rem .8rem;
  font: inherit;
}

.tf-segmented {
  display: flex;
  gap: .45rem;
  flex-wrap: wrap;
}

.tf-segmented input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.tf-segmented span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.7rem;
  border: 1px solid rgba(23, 63, 53, .18);
  background: #fff;
  color: var(--tf-green);
  border-radius: .75rem;
  padding: .65rem .85rem;
  font-weight: 900;
  cursor: pointer;
}

.tf-segmented input:checked + span {
  background: var(--tf-green);
  color: #fff;
  border-color: var(--tf-green);
}

.tf-wizard-info {
  background: #fff;
  color: #5f6b65;
  border-radius: .85rem;
  padding: .8rem;
  font-size: .92rem;
}

.mt-small {
  margin-top: .65rem;
}

.tf-action-button.secondary {
  background: #fff;
  color: var(--tf-green);
  border: 1px solid rgba(30, 61, 28, .18);
}
CSS;

            $write($cssFile, $css);
        }
    }

    $write($root . '/docs/node-creation-wizard.md', <<<'MD'
# Node Creation Wizard

Patch 027 ergänzt das Anlegen neuer Nodes im Explorer.

## Button

```text
+ Node
```

## Node-Typen

```text
Text
Image
Button
Markdown
CSS
Columns
```

## Parent Logik

Wenn keine Node markiert ist, wird die neue Node am Ende der Seite angelegt.

Wenn eine Node markiert ist, wird die neue Node als Child dieser Node angelegt.

## Columns

Bei Columns kann die Spaltenanzahl gewählt werden:

```text
2 bis 6 Spalten
```

TreeForge erzeugt automatisch:

```text
Columns
├─ Column
├─ Column
└─ Column
```

## API

```text
POST /api/node/create.php
```

Beispiel:

```json
{
  "page": "home",
  "parent": "node_column_1",
  "type": "text"
}
```

MD);

    $log('Patch 027 Node Creation Wizard fertig');
};
