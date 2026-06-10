<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 017
 * Fix Async Text Save + Cache Bust
 *
 * Problem:
 * - Browser lädt eventuell noch altes explorer.js
 * - alter JS-Code macht Reload nach Save
 * - API/PageEditor werden nochmals robust gesetzt
 *
 * Ergebnis:
 * - Save per fetch()
 * - kein Reload
 * - Fokus bleibt im Textfeld
 * - sichtbare Toast-Meldungen
 * - cache-busted explorer.js?v=017
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

    $log('Patch 017 Fix Async Text Save gestartet');

    $write($root . '/app/Core/PageEditor.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class PageEditor
{
    public static function updateTextNodeContent(array &$pageData, string $nodeId, string $content): array
    {
        foreach ($pageData['children'] ?? [] as &$node) {
            if (!is_array($node)) {
                continue;
            }

            $updatedNode = self::updateTextNodeRecursive($node, $nodeId, $content);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

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

        foreach ($node['children'] ?? [] as &$child) {
            if (!is_array($child)) {
                continue;
            }

            $updatedNode = self::updateTextNodeRecursive($child, $nodeId, $content);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        return null;
    }
}
PHP);

    $write($root . '/public/api/node/save-text.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Core\NodeInspector;
use TreeForge\Core\PageEditor;
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
    $nodeId = (string)($payload['node'] ?? '');
    $content = (string)($payload['content'] ?? '');

    if ($nodeId === '') {
        throw new RuntimeException('Missing node id');
    }

    $workspace = Workspace::draft($root);
    $workspace->ensurePage($pageId);

    $file = $workspace->pagePath($pageId);
    $pageData = json_decode((string)file_get_contents($file), true);

    if (!is_array($pageData)) {
        throw new RuntimeException('Invalid page JSON');
    }

    $updatedNode = PageEditor::updateTextNodeContent($pageData, $nodeId, $content);

    $workspace->savePage($pageId, $pageData);

    echo json_encode([
        'ok' => true,
        'message' => 'TextNode im Draft gespeichert.',
        'workspace' => 'draft',
        'page' => $pageId,
        'node' => $nodeId,
        'inspector' => NodeInspector::inspectArray($updatedNode),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
PHP);

    $write($root . '/public/assets/js/explorer.js', <<<'JS'
(function () {
  const buttons = document.querySelectorAll('.tf-tree-node-button');
  const empty = document.getElementById('tfInspectorEmpty');
  const content = document.getElementById('tfInspectorContent');
  const idTarget = document.getElementById('tfInspectorId');
  const typeTarget = document.getElementById('tfInspectorType');
  const childrenTarget = document.getElementById('tfInspectorChildren');
  const jsonTarget = document.getElementById('tfInspectorJson');
  const propertiesTarget = document.getElementById('tfInspectorProperties');
  const previewSection = document.getElementById('tfPreviewSection');
  const previewCode = document.getElementById('tfPreviewCode');
  const textEditorSection = document.getElementById('tfTextEditorSection');
  const textEditor = document.getElementById('tfTextEditor');
  const saveButton = document.getElementById('tfSaveTextNode');
  const saveStatus = document.getElementById('tfSaveStatus');
  const inspectorMode = document.getElementById('tfInspectorMode');

  let selectedNode = null;
  let selectedButton = null;

  function ensureNoticeRoot() {
    let root = document.getElementById('tfNoticeRoot');

    if (!root) {
      root = document.createElement('div');
      root.id = 'tfNoticeRoot';
      root.className = 'tf-notice-root';
      document.body.appendChild(root);
    }

    return root;
  }

  function showNotice(type, message) {
    const root = ensureNoticeRoot();
    const notice = document.createElement('div');
    notice.className = 'tf-toast tf-toast-' + type;
    notice.textContent = message;
    root.appendChild(notice);

    setTimeout(() => {
      notice.classList.add('hide');
      setTimeout(() => notice.remove(), 350);
    }, 3500);
  }

  function valueToString(value) {
    if (value === null) return 'null';
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
  }

  function renderProperties(properties) {
    propertiesTarget.innerHTML = '';
    const keys = Object.keys(properties || {});

    if (keys.length === 0) {
      const emptyRow = document.createElement('div');
      emptyRow.className = 'tf-property-empty';
      emptyRow.textContent = 'Keine Properties vorhanden.';
      propertiesTarget.appendChild(emptyRow);
      return;
    }

    keys.forEach((key) => {
      const row = document.createElement('div');
      row.className = 'tf-property-row';

      const name = document.createElement('div');
      name.className = 'tf-property-name';
      name.textContent = key;

      const value = document.createElement('pre');
      value.className = 'tf-property-value';
      value.textContent = valueToString(properties[key]);

      row.appendChild(name);
      row.appendChild(value);
      propertiesTarget.appendChild(row);
    });
  }

  function renderPreview(preview) {
    if (!preview || preview.kind !== 'code') {
      previewSection.hidden = true;
      previewCode.textContent = '';
      previewCode.className = '';
      return;
    }

    const lang = preview.language || 'markup';
    previewCode.textContent = preview.content || '';
    previewCode.className = 'language-' + lang;
    previewSection.hidden = false;

    if (window.Prism) {
      Prism.highlightElement(previewCode);
    }
  }

  function renderTextEditor(data) {
    const isText = data && data.type === 'text';
    const workspace = (window.TreeForgeExplorer && window.TreeForgeExplorer.workspace) || 'published';
    const archive = (window.TreeForgeExplorer && window.TreeForgeExplorer.archive) || '';

    if (!isText || archive !== '') {
      textEditorSection.hidden = true;
      inspectorMode.textContent = 'readonly';
      return;
    }

    textEditor.value = (data.properties && data.properties.content) ? data.properties.content : '';
    textEditorSection.hidden = false;

    if (workspace === 'draft') {
      saveButton.disabled = false;
      inspectorMode.textContent = 'editable';
      saveStatus.textContent = '';
    } else {
      saveButton.disabled = true;
      inspectorMode.textContent = 'readonly';
      saveStatus.textContent = 'Zum Bearbeiten Draft Workspace öffnen.';
    }
  }

  function renderInspector(data, keepTextFocus) {
    selectedNode = data;

    idTarget.textContent = data.id || '–';
    typeTarget.textContent = data.type || 'unknown';
    childrenTarget.textContent = data.children_count ?? 0;

    renderPreview(data.preview || {});
    renderProperties(data.properties || {});
    jsonTarget.textContent = JSON.stringify(data.raw || data, null, 2);

    const cursorStart = textEditor ? textEditor.selectionStart : null;
    const cursorEnd = textEditor ? textEditor.selectionEnd : null;

    renderTextEditor(data);

    if (keepTextFocus && textEditor && !textEditorSection.hidden) {
      textEditor.focus();
      if (cursorStart !== null && cursorEnd !== null) {
        textEditor.setSelectionRange(cursorStart, cursorEnd);
      }
    }

    empty.hidden = true;
    content.hidden = false;
  }

  function updateSelectedButtonData(data) {
    if (!selectedButton) {
      return;
    }

    selectedButton.setAttribute('data-node-json', JSON.stringify(data));
  }

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      buttons.forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      selectedButton = button;

      const raw = button.getAttribute('data-node-json') || '{}';
      let data;

      try {
        data = JSON.parse(raw);
      } catch (error) {
        data = { id: '–', type: 'unknown', properties: {}, preview: {}, children_count: 0, raw: raw };
      }

      renderInspector(data, false);
    });
  });

  if (saveButton) {
    saveButton.addEventListener('click', async () => {
      if (!selectedNode || selectedNode.type !== 'text') {
        return;
      }

      const oldText = saveButton.textContent;
      saveButton.disabled = true;
      saveButton.textContent = 'Speichere ...';
      saveStatus.textContent = 'Speichere im Draft ...';

      try {
        const response = await fetch('/api/node/save-text.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            page: (window.TreeForgeExplorer && window.TreeForgeExplorer.page) || 'home',
            node: selectedNode.id,
            content: textEditor.value
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
          throw new Error(result.error || 'Fehler beim Speichern');
        }

        if (result.inspector) {
          selectedNode = result.inspector;
          updateSelectedButtonData(result.inspector);
          renderInspector(result.inspector, true);
        }

        saveStatus.textContent = 'Gespeichert.';
        showNotice('success', result.message || 'Gespeichert.');

      } catch (error) {
        saveStatus.textContent = error.message;
        showNotice('error', error.message);
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = oldText;
      }
    });
  }

  document.querySelectorAll('[data-workflow-action]').forEach((button) => {
    button.addEventListener('click', async () => {
      const action = button.getAttribute('data-workflow-action');
      if (!action) return;

      button.disabled = true;
      const oldText = button.textContent;
      button.textContent = 'Bitte warten ...';

      try {
        const response = await fetch('/api/workflow/action.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            action: action,
            page: (window.TreeForgeExplorer && window.TreeForgeExplorer.page) || 'home'
          })
        });

        const result = await response.json();

        if (!result.ok) throw new Error(result.error || 'Workflow Fehler');

        showNotice('success', result.message || 'Workflow ausgeführt.');
        setTimeout(() => {
          window.location.href = '/explorer?workspace=' + encodeURIComponent(result.target);
        }, 500);

      } catch (error) {
        button.disabled = false;
        button.textContent = oldText;
        showNotice('error', error.message);
      }
    });
  });

  document.querySelectorAll('[data-archive-restore]').forEach((button) => {
    button.addEventListener('click', async () => {
      const version = button.getAttribute('data-archive-restore');
      if (!version) return;

      if (!confirm('Diese Archivversion wirklich nach Published wiederherstellen? Die aktuelle Published-Version wird vorher archiviert.')) {
        return;
      }

      button.disabled = true;
      const oldText = button.textContent;
      button.textContent = 'Wiederherstellen ...';

      try {
        const response = await fetch('/api/archive/restore.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            page: (window.TreeForgeExplorer && window.TreeForgeExplorer.page) || 'home',
            version: version
          })
        });

        const result = await response.json();

        if (!result.ok) throw new Error(result.error || 'Restore Fehler');

        showNotice('success', result.message || 'Archivversion wiederhergestellt.');
        setTimeout(() => {
          window.location.href = '/explorer?workspace=published';
        }, 500);

      } catch (error) {
        button.disabled = false;
        button.textContent = oldText;
        showNotice('error', error.message);
      }
    });
  });
})();
JS);

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        $renderer = str_replace(
            '<script src="/assets/js/explorer.js"></script>',
            '<script src="/assets/js/explorer.js?v=017"></script>',
            $renderer
        );

        $renderer = str_replace(
            '<script src="/assets/js/explorer.js?v=016"></script>',
            '<script src="/assets/js/explorer.js?v=017"></script>',
            $renderer
        );

        $write($rendererFile, $renderer);
    }

    $cssFile = $root . '/public/assets/css/explorer.css';

    if (file_exists($cssFile)) {
        $css = file_get_contents($cssFile);

        if (!str_contains($css, '.tf-notice-root')) {
            $css .= <<<'CSS_APPEND'

.tf-notice-root {
  position: fixed;
  right: 1rem;
  bottom: 1rem;
  z-index: 9999;
  display: grid;
  gap: .6rem;
  max-width: min(420px, calc(100vw - 2rem));
}

.tf-toast {
  padding: .85rem 1rem;
  border-radius: .9rem;
  color: #fff;
  font-weight: 800;
  box-shadow: 0 .7rem 2rem rgba(0, 0, 0, .18);
  opacity: 1;
  transform: translateY(0);
  transition: opacity .25s ease, transform .25s ease;
}

.tf-toast.hide {
  opacity: 0;
  transform: translateY(10px);
}

.tf-toast-success {
  background: var(--tf-green);
}

.tf-toast-error {
  background: #9b1c1c;
}
CSS_APPEND;

            $write($cssFile, $css);
        }
    }

    $write($root . '/docs/fix-async-text-save.md', <<<'MD'
# Fix Async Text Save

Patch 017 behebt den alten Reload beim Speichern.

## Wichtig

Wenn nach dem Patch weiterhin die Meldung

```text
Gespeichert. Seite wird neu geladen ...
```

erscheint, lädt der Browser noch ein altes JavaScript.

Dann hart neu laden:

```text
Strg + F5
```

oder prüfen, ob im Quellcode steht:

```html
/assets/js/explorer.js?v=017
```

## Test

```text
/explorer?workspace=draft
```

TextNode wählen, Text ändern, speichern.

Erwartung:

- kein Reload
- Toast "TextNode im Draft gespeichert."
- Fokus bleibt im Textfeld
- `storage/workspaces/draft/pages/home.json` enthält den neuen Text

MD);

    $log('Patch 017 Fix Async Text Save fertig');
};
