(function () {
  let targetParentId = '';
  let selectedType = '';

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function toast(message) {
    let box = document.getElementById('tfv2NodeActionToast');

    if (!box) {
      box = document.createElement('div');
      box.id = 'tfv2NodeActionToast';
      box.className = 'tfv2-node-action-toast';
      document.body.appendChild(box);
    }

    box.textContent = message;
    box.classList.add('show');

    window.setTimeout(() => {
      box.classList.remove('show');
    }, 4500);
  }

  function ensureDialog() {
    let dialog = document.getElementById('tfv2AddNodeDialog');

    if (dialog) {
      return dialog;
    }

    dialog = document.createElement('div');
    dialog.id = 'tfv2AddNodeDialog';
    dialog.className = 'tfv2-add-node-dialog';
    dialog.hidden = true;
    dialog.innerHTML = `
      <div class="tfv2-add-node-backdrop" data-add-node-close></div>
      <div class="tfv2-add-node-panel" role="dialog" aria-modal="true" aria-label="Neue Node hinzufügen">
        <header class="tfv2-add-node-head">
          <div>
            <strong>Neue Node hinzufügen</strong>
            <span id="tfv2AddNodeTarget">Parent: –</span>
          </div>
          <button type="button" class="tfv2-add-node-close" data-add-node-close>×</button>
        </header>

        <div class="tfv2-add-node-body">
          <div class="tfv2-add-node-types" id="tfv2AddNodeTypes"></div>

          <aside class="tfv2-add-node-preview">
            <div class="tfv2-add-node-empty" id="tfv2AddNodePreview">
              Node-Typ auswählen.
            </div>
          </aside>
        </div>

        <footer class="tfv2-add-node-foot">
          <button type="button" class="tfv2-btn secondary" data-add-node-close>Abbrechen</button>
          <button type="button" class="tfv2-btn" id="tfv2AddNodeCreate" disabled>Hinzufügen vorbereiten</button>
        </footer>
      </div>
    `;

    document.body.appendChild(dialog);

    dialog.addEventListener('click', event => {
      if (event.target.closest('[data-add-node-close]')) {
        closeDialog();
      }

      const typeButton = event.target.closest('[data-node-type]');
      if (typeButton) {
        selectType(typeButton.dataset.nodeType || '');
      }
    });

    dialog.querySelector('#tfv2AddNodeCreate')?.addEventListener('click', () => {
      const registry = window.TreeForgeV2NodeTypeRegistry;
      const item = registry ? registry.byType(selectedType) : null;

      if (!item) {
        toast('Bitte Node-Typ auswählen.');
        return;
      }

      const payload = {
        parent_id: targetParentId,
        type: item.type,
        mode: 'append',
        defaults: item.defaults || {}
      };

      window.TreeForgeV2PendingNodeCreate = payload;
      document.dispatchEvent(new CustomEvent('tfv2:add-node-prepared', { detail: payload }));

      toast('Node vorbereitet: ' + item.label + ' → ' + targetParentId);
      closeDialog();
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !dialog.hidden) {
        closeDialog();
      }
    });

    return dialog;
  }

  function groupedTypes() {
    const registry = window.TreeForgeV2NodeTypeRegistry;
    const all = registry ? registry.all() : [];
    const groups = {};

    all.forEach(item => {
      const group = item.group || 'Other';
      groups[group] = groups[group] || [];
      groups[group].push(item);
    });

    return groups;
  }

  function renderTypes() {
    const box = document.getElementById('tfv2AddNodeTypes');

    if (!box) {
      return;
    }

    const groups = groupedTypes();
    let html = '';

    Object.keys(groups).sort().forEach(group => {
      html += `<section class="tfv2-node-type-group"><h3>${escapeHtml(group)}</h3><div>`;

      groups[group].forEach(item => {
        html += `
          <button type="button" class="tfv2-node-type-card" data-node-type="${escapeHtml(item.type)}">
            <span>${escapeHtml(item.icon || '📦')}</span>
            <strong>${escapeHtml(item.label || item.type)}</strong>
            <small>${escapeHtml(item.description || '')}</small>
          </button>
        `;
      });

      html += '</div></section>';
    });

    box.innerHTML = html || '<div class="tfv2-empty">Keine Node-Typen registriert.</div>';
  }

  function selectType(type) {
    selectedType = type;

    document.querySelectorAll('.tfv2-node-type-card').forEach(card => {
      card.classList.toggle('active', card.dataset.nodeType === type);
    });

    const registry = window.TreeForgeV2NodeTypeRegistry;
    const item = registry ? registry.byType(type) : null;
    const preview = document.getElementById('tfv2AddNodePreview');
    const create = document.getElementById('tfv2AddNodeCreate');

    if (create) {
      create.disabled = !item;
    }

    if (!preview || !item) {
      return;
    }

    preview.innerHTML = `
      <div class="tfv2-add-node-preview-card">
        <div class="tfv2-add-node-preview-icon">${escapeHtml(item.icon || '📦')}</div>
        <h3>${escapeHtml(item.label || item.type)}</h3>
        <p>${escapeHtml(item.description || '')}</p>
        <dl>
          <dt>Type</dt><dd><code>${escapeHtml(item.type)}</code></dd>
          <dt>Parent</dt><dd><code>${escapeHtml(targetParentId)}</code></dd>
          <dt>Mode</dt><dd>append</dd>
        </dl>
        <pre>${escapeHtml(JSON.stringify(item.defaults || {}, null, 2))}</pre>
      </div>
    `;
  }

  function openDialog(parentId) {
    targetParentId = parentId || '';
    selectedType = '';

    const dialog = ensureDialog();

    const target = dialog.querySelector('#tfv2AddNodeTarget');
    if (target) {
      target.textContent = 'Parent: ' + (targetParentId || 'Root');
    }

    renderTypes();

    const create = dialog.querySelector('#tfv2AddNodeCreate');
    if (create) {
      create.disabled = true;
    }

    const preview = dialog.querySelector('#tfv2AddNodePreview');
    if (preview) {
      preview.textContent = 'Node-Typ auswählen.';
    }

    dialog.hidden = false;
    document.body.classList.add('tfv2-add-node-open');
  }

  function closeDialog() {
    const dialog = document.getElementById('tfv2AddNodeDialog');

    if (!dialog) {
      return;
    }

    dialog.hidden = true;
    document.body.classList.remove('tfv2-add-node-open');
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-node-action="add-child"]');

    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    openDialog(button.dataset.nodeId || '');
  }, true);

  window.TreeForgeV2AddNodeDialog = {
    open: openDialog,
    close: closeDialog
  };
})();
/* PATCH 092 ADD NODE API INTEGRATION */
(function () {
  function patchCreateButton() {
    const button = document.getElementById('tfv2AddNodeCreate');

    if (!button || button.dataset.apiPatched === '1') {
      return;
    }

    button.dataset.apiPatched = '1';

    button.addEventListener('click', async function (event) {
      const pending = window.TreeForgeV2PendingNodeCreate;

      if (!pending || !pending.type || !pending.parent_id) {
        return;
      }

      if (!window.TreeForgeV2Mutations) {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();

      button.disabled = true;
      const originalText = button.textContent;
      button.textContent = 'Wird hinzugefügt...';

      try {
        await window.TreeForgeV2Mutations.mutate('add', {
          parent_id: pending.parent_id,
          type: pending.type,
          defaults: pending.defaults || {}
        });

        const newNodeId = json && json.result && json.result.node_id ? json.result.node_id : '';
        if (newNodeId) {
          sessionStorage.setItem('tfv2.lastAddedNode', newNodeId);
        }
        localStorage.removeItem('tfv2.collapsedNodes.' + (window.TreeForgeV2Mutations.currentPage ? window.TreeForgeV2Mutations.currentPage() : 'home'));
        window.TreeForgeV2Mutations.toast('Node wurde hinzugefügt.');
        window.setTimeout(() => {
          const url = new URL(window.location.href);
          url.searchParams.set('workspace', 'draft');
          url.searchParams.set('_', String(Date.now()));
          window.location.href = url.toString();
        }, 2500);
      } catch (error) {
        window.TreeForgeV2Mutations.toast(error.message || 'Node konnte nicht hinzugefügt werden.', 'error');
        button.disabled = false;
        button.textContent = originalText;
      }
    }, true);
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-node-action="add-child"]')) {
      window.setTimeout(patchCreateButton, 80);
      window.setTimeout(patchCreateButton, 250);
    }
  }, true);

  document.addEventListener('tfv2:add-node-prepared', async function (event) {
    const payload = event.detail || {};

    if (!window.TreeForgeV2Mutations || !payload.type || !payload.parent_id) {
      return;
    }

    try {
      await window.TreeForgeV2Mutations.mutate('add', {
        parent_id: payload.parent_id,
        type: payload.type,
        defaults: payload.defaults || {}
      });

      window.TreeForgeV2Mutations.toast('Node wurde hinzugefügt.');
      window.setTimeout(() => {
        window.TreeForgeV2Mutations.reloadKeepingState();
      }, 2500);
    } catch (error) {
      window.TreeForgeV2Mutations.toast(error.message || 'Node konnte nicht hinzugefügt werden.', 'error');
    }
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', patchCreateButton);
  } else {
    patchCreateButton();
  }
})();