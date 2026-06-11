<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 028
 * Fix Node Wizard Injection
 *
 * Problem:
 * Patch 027 hat den Wizard evtl. nicht eingefügt, weil Toolbar-HTML nicht exakt gefunden wurde.
 *
 * Fix:
 * - ExplorerRenderer.php wird gezielt und robust gepatcht
 * - + Node Button wird sicher eingefügt
 * - Modal wird sicher vor </body> eingefügt
 * - explorer.js bekommt Wizard-Code sicher angehängt
 * - Cache-Buster auf v028
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

    $log('Patch 028 Fix Node Wizard Injection gestartet');

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (!file_exists($rendererFile)) {
        throw new RuntimeException('ExplorerRenderer.php nicht gefunden.');
    }

    $renderer = file_get_contents($rendererFile);

    // 1) + Node Button sicher in vorhandene Toolbar einfügen.
    if (!str_contains($renderer, 'id="tfAddNode"')) {
        if (str_contains($renderer, 'id="tfExpandAll"')) {
            $renderer = str_replace(
                '<button type="button" class="tf-tree-tool" id="tfExpandAll">Alle aufklappen</button>',
                '<button type="button" class="tf-tree-tool primary" id="tfAddNode">+ Node</button><button type="button" class="tf-tree-tool" id="tfExpandAll">Alle aufklappen</button>',
                $renderer
            );
            $log('+ Node Button vor Alle aufklappen eingefügt.');
        } else {
            // Fallback: Toolbar vor Tree einsetzen
            $renderer = str_replace(
                '{$tree}',
                '<div class="tf-tree-toolbar"><button type="button" class="tf-tree-tool primary" id="tfAddNode">+ Node</button><button type="button" class="tf-tree-tool" id="tfExpandAll">Alle aufklappen</button><button type="button" class="tf-tree-tool" id="tfCollapseAll">Alle zuklappen</button></div>' . "\n\n      " . '{$tree}',
                $renderer
            );
            $log('+ Node Toolbar per Fallback eingefügt.');
        }
    } else {
        $log('+ Node Button bereits vorhanden.');
    }

    // 2) Modal sicher vor </body> einfügen.
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

        $renderer = str_replace('</body>', $modal . "\n</body>", $renderer);
        $log('Node Wizard Modal eingefügt.');
    } else {
        $log('Node Wizard Modal bereits vorhanden.');
    }

    // 3) Cache-Buster aktualisieren.
    $renderer = preg_replace(
        '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
        '<script src="/assets/js/explorer.js?v=028"></script>',
        $renderer
    );

    $write($rendererFile, $renderer);

    // 4) JS Wizard sicher einfügen.
    $jsFile = $root . '/public/assets/js/explorer.js';

    if (!file_exists($jsFile)) {
        throw new RuntimeException('explorer.js nicht gefunden.');
    }

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
      console.warn('TreeForge Node Wizard nicht vollständig gefunden.');
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

    if (closeButton) closeButton.addEventListener('click', closeModal);
    if (cancelButton) cancelButton.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeModal();
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

        $pos = strrpos($js, '})();');

        if ($pos === false) {
            throw new RuntimeException('Kann Ende von explorer.js nicht finden.');
        }

        $js = substr($js, 0, $pos) . $wizardJs . "\n" . substr($js, $pos);
        $write($jsFile, $js);
        $log('Node Wizard JS eingefügt.');
    } else {
        $log('Node Wizard JS bereits vorhanden.');
    }

    // 5) CSS sicher ergänzen.
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
            $log('Node Wizard CSS ergänzt.');
        } else {
            $log('Node Wizard CSS bereits vorhanden.');
        }
    }

    $write($root . '/docs/fix-node-wizard-injection.md', <<<'MD'
# Fix Node Wizard Injection

Patch 028 fügt den Node Wizard robuster ein.

## Prüfen

Im Seitenquelltext muss vorhanden sein:

```html
id="tfAddNode"
id="tfNodeWizard"
explorer.js?v=028
```

In der JS-Datei muss vorhanden sein:

```js
function initNodeWizard
```

## Test

```text
/explorer?workspace=draft
```

Dann auf `+ Node` klicken.

MD);

    $log('Patch 028 Fix Node Wizard Injection fertig');
};
