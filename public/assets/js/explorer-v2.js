(function () {
  const shell = document.getElementById('tfv2Shell');
  const resizer = document.getElementById('tfv2Resizer');
  if (shell && resizer) {
    const savedWidth = localStorage.getItem('tfv2.sidebarWidth');
    const collapsed = localStorage.getItem('tfv2.sidebarCollapsed') === '1';
    if (savedWidth) shell.style.setProperty('--tfv2-sidebar-width', savedWidth + 'px');
    if (collapsed) shell.classList.add('sidebar-collapsed');
    let dragging = false;
    resizer.addEventListener('mousedown', function (event) {
      if (shell.classList.contains('sidebar-collapsed')) {
        shell.classList.remove('sidebar-collapsed');
        localStorage.setItem('tfv2.sidebarCollapsed', '0');
      }
      dragging = true;
      resizer.classList.add('dragging');
      document.body.style.userSelect = 'none';
      event.preventDefault();
    });
    resizer.addEventListener('dblclick', function () {
      shell.classList.toggle('sidebar-collapsed');
      localStorage.setItem('tfv2.sidebarCollapsed', shell.classList.contains('sidebar-collapsed') ? '1' : '0');
    });
    window.addEventListener('mousemove', function (event) {
      if (!dragging) return;
      const width = Math.max(280, Math.min(700, event.clientX));
      shell.style.setProperty('--tfv2-sidebar-width', width + 'px');
      localStorage.setItem('tfv2.sidebarWidth', String(width));
    });
    window.addEventListener('mouseup', function () {
      if (!dragging) return;
      dragging = false;
      resizer.classList.remove('dragging');
      document.body.style.userSelect = '';
    });
  }
  document.querySelectorAll('.tfv2-tabs button').forEach(button => {
    button.addEventListener('click', function () {
      const tab = button.dataset.tab;
      document.querySelectorAll('.tfv2-tabs button').forEach(item => item.classList.remove('active'));
      document.querySelectorAll('.tfv2-tab-panel').forEach(panel => panel.classList.remove('active'));
      button.classList.add('active');
      const panel = document.querySelector('.tfv2-tab-panel[data-panel="' + tab + '"]');
      if (panel) panel.classList.add('active');
    });
  });
  document.querySelectorAll('.tfv2-node').forEach(node => {
    node.addEventListener('click', function () {
      document.querySelectorAll('.tfv2-node').forEach(item => item.classList.remove('active'));
      node.classList.add('active');
      const selected = document.getElementById('tfv2SelectedNode');
      if (selected) selected.textContent = node.dataset.nodeId || 'Node';
    });
  });
})();
/* PATCH 075 COLLAPSIBLE NODE TREE */
(function () {
  const pageId = new URLSearchParams(window.location.search).get('page') || 'home';
  const storageKey = 'tfv2.collapsedNodes.' + pageId;

  function readState() {
    try {
      return JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
    } catch (error) {
      return {};
    }
  }

  function writeState(state) {
    localStorage.setItem(storageKey, JSON.stringify(state));
  }

  const state = readState();

  document.querySelectorAll('[data-node-wrap]').forEach(wrap => {
    const id = wrap.dataset.nodeWrap;

    if (state[id]) {
      wrap.classList.add('is-collapsed');
    }
  });

  document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-node-toggle]');

    if (!toggle) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const id = toggle.dataset.nodeToggle;
    const wrap = document.querySelector('[data-node-wrap="' + CSS.escape(id) + '"]');

    if (!wrap) {
      return;
    }

    wrap.classList.toggle('is-collapsed');

    const nextState = readState();
    nextState[id] = wrap.classList.contains('is-collapsed');

    if (!nextState[id]) {
      delete nextState[id];
    }

    writeState(nextState);
  });

  const expandAll = document.getElementById('tfv2ExpandAll');
  const collapseAll = document.getElementById('tfv2CollapseAll');

  if (expandAll) {
    expandAll.addEventListener('click', () => {
      document.querySelectorAll('[data-node-wrap]').forEach(wrap => wrap.classList.remove('is-collapsed'));
      writeState({});
    });
  }

  if (collapseAll) {
    collapseAll.addEventListener('click', () => {
      const nextState = {};

      document.querySelectorAll('[data-node-wrap][data-has-children="1"]').forEach(wrap => {
        const id = wrap.dataset.nodeWrap;
        wrap.classList.add('is-collapsed');
        nextState[id] = true;
      });

      writeState(nextState);
    });
  }
})();
/* PATCH 077 TYPE EDITORS */
(function () {
  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function nodeType(node) {
    return String(node.type || '').toLowerCase();
  }

  function pick(node, keys, fallback) {
    for (const key of keys) {
      if (typeof node[key] !== 'undefined' && node[key] !== null) {
        return node[key];
      }
    }
    return fallback ?? '';
  }

  function renderText(node) {
    const value = pick(node, ['content', 'text', 'value'], '');
    return `
      <label class="tfv2-field-full">
        Text
        <textarea id="tfv2NodeContent" rows="10">${escapeHtml(value)}</textarea>
        <small>Einfacher Textinhalt dieser Node.</small>
      </label>
    `;
  }

  function renderMarkdown(node) {
    const value = pick(node, ['markdown', 'content', 'text', 'value'], '');
    return `
      <label class="tfv2-field-full">
        Markdown
        <textarea id="tfv2NodeContent" class="code" rows="14">${escapeHtml(value)}</textarea>
        <small>Markdown wird später serverseitig gerendert.</small>
      </label>
    `;
  }

  function renderCss(node) {
    const value = pick(node, ['css', 'content', 'value'], '');
    return `
      <label class="tfv2-field-full">
        CSS
        <textarea id="tfv2NodeContent" class="code" rows="14">${escapeHtml(value)}</textarea>
        <small>CSS sollte später validiert und begrenzt werden.</small>
      </label>
    `;
  }

  function renderImage(node) {
    const mediaId = pick(node, ['media_id', 'mediaId', 'image', 'src', 'value'], '');
    const alt = pick(node, ['alt', 'alt_text', 'altText'], '');
    const caption = pick(node, ['caption'], '');
    const display = pick(node, ['display', 'size'], 'content');

    return `
      <div class="tfv2-media-preview" id="tfv2ImagePreview">Bildvorschau später</div>

      <label class="tfv2-field-full">
        Media ID / Pfad
        <div class="tfv2-inline-field">
          <input id="tfv2ImageMediaId" value="${escapeHtml(mediaId)}">
          <button type="button" class="tfv2-btn secondary" id="tfv2PickMedia">Medien wählen</button>
        </div>
        <small>Später direkt mit der Media Library verbunden.</small>
      </label>

      <div class="tfv2-field-grid">
        <label>
          Darstellung
          <select id="tfv2ImageDisplay">
            <option value="content"${display === 'content' ? ' selected' : ''}>Content</option>
            <option value="hero"${display === 'hero' ? ' selected' : ''}>Hero</option>
            <option value="large"${display === 'large' ? ' selected' : ''}>Large</option>
            <option value="social"${display === 'social' ? ' selected' : ''}>Social</option>
          </select>
        </label>
        <label>
          Zoom
          <select id="tfv2ImageZoom">
            <option value="">Nein</option>
            <option value="lightbox">Lightbox</option>
            <option value="bump">Bump</option>
          </select>
        </label>
      </div>

      <label class="tfv2-field-full">
        Alt-Text
        <input id="tfv2ImageAlt" value="${escapeHtml(alt)}">
      </label>

      <label class="tfv2-field-full">
        Caption
        <textarea id="tfv2ImageCaption" rows="4">${escapeHtml(caption)}</textarea>
      </label>
    `;
  }

  function renderButton(node) {
    const label = pick(node, ['label', 'text', 'title'], '');
    const url = pick(node, ['url', 'href', 'link'], '');
    const target = pick(node, ['target'], '_self');

    return `
      <div class="tfv2-field-grid">
        <label>
          Button Text
          <input id="tfv2ButtonLabel" value="${escapeHtml(label)}">
        </label>
        <label>
          Ziel
          <select id="tfv2ButtonTarget">
            <option value="_self"${target === '_self' ? ' selected' : ''}>Gleiches Fenster</option>
            <option value="_blank"${target === '_blank' ? ' selected' : ''}>Neues Fenster</option>
          </select>
        </label>
      </div>
      <label class="tfv2-field-full">
        URL
        <input id="tfv2ButtonUrl" value="${escapeHtml(url)}">
      </label>
    `;
  }

  function renderColumns(node) {
    const count = pick(node, ['columns', 'count'], Array.isArray(node.children) ? node.children.length : 2);
    const gap = pick(node, ['gap'], '1rem');

    return `
      <div class="tfv2-field-grid">
        <label>
          Spalten
          <select id="tfv2ColumnsCount">
            ${[2,3,4,5,6].map(n => `<option value="${n}"${Number(count) === n ? ' selected' : ''}>${n}</option>`).join('')}
          </select>
        </label>
        <label>
          Gap
          <input id="tfv2ColumnsGap" value="${escapeHtml(gap)}">
        </label>
      </div>
      <div class="tfv2-empty">Children werden im Node Tree gepflegt.</div>
    `;
  }

  function renderDefault(node) {
    const value = pick(node, ['content', 'text', 'value', 'html'], '');
    return `
      <label class="tfv2-field-full">
        Content / Value
        <textarea id="tfv2NodeContent" rows="10">${escapeHtml(value)}</textarea>
      </label>
    `;
  }

  function renderTypeEditor(node) {
    const target = document.getElementById('tfv2TypeEditor');

    if (!target) return;

    const type = nodeType(node);

    if (type.includes('markdown')) {
      target.innerHTML = renderMarkdown(node);
    } else if (type.includes('image')) {
      target.innerHTML = renderImage(node);
      bindImagePicker();
    } else if (type.includes('button')) {
      target.innerHTML = renderButton(node);
    } else if (type.includes('columns') || type === 'column') {
      target.innerHTML = renderColumns(node);
    } else if (type.includes('css')) {
      target.innerHTML = renderCss(node);
    } else if (type.includes('text')) {
      target.innerHTML = renderText(node);
    } else {
      target.innerHTML = renderDefault(node);
    }
  }

  function bindImagePicker() {
    const button = document.getElementById('tfv2PickMedia');
    const input = document.getElementById('tfv2ImageMediaId');
    const preview = document.getElementById('tfv2ImagePreview');

    if (!button || !input) return;

    button.addEventListener('click', () => {
      if (!window.TreeForgeMediaPicker) {
        alert('Media Picker ist noch nicht geladen.');
        return;
      }

      window.TreeForgeMediaPicker.open(function (media) {
        input.value = media.id || media.relative_path || media.url || '';

        if (preview && media.preview_url) {
          preview.innerHTML = '<img src="' + media.preview_url + '" alt="">';
        }
      });
    });
  }

  document.addEventListener('click', event => {
    const nodeElement = event.target.closest('.tfv2-node[data-node-json]');

    if (!nodeElement || event.target.closest('[data-node-toggle]')) {
      return;
    }

    try {
      const node = JSON.parse(nodeElement.dataset.nodeJson || '{}');
      window.setTimeout(() => renderTypeEditor(node), 0);
    } catch (error) {
      // ignore
    }
  });

  const firstNode = document.querySelector('.tfv2-node[data-node-json]');
  if (firstNode) {
    try {
      renderTypeEditor(JSON.parse(firstNode.dataset.nodeJson || '{}'));
    } catch (error) {
      // ignore
    }
  }
})();
/* PATCH 080 RESIZABLE NODE TREE PANEL */
(function () {
  const grid = document.querySelector('.tfv2-main-grid');
  const resizer = document.getElementById('tfv2NodeResizer');

  if (!grid || !resizer) {
    return;
  }

  const widthKey = 'tfv2.nodeTreeWidth';
  const collapsedKey = 'tfv2.nodeTreeCollapsed';

  const savedWidth = localStorage.getItem(widthKey);
  const collapsed = localStorage.getItem(collapsedKey) === '1';

  if (savedWidth) {
    grid.style.setProperty('--tfv2-node-tree-width', savedWidth + 'px');
  }

  if (collapsed) {
    grid.classList.add('node-tree-collapsed');
  }

  let dragging = false;

  resizer.addEventListener('mousedown', function (event) {
    if (grid.classList.contains('node-tree-collapsed')) {
      grid.classList.remove('node-tree-collapsed');
      localStorage.setItem(collapsedKey, '0');
    }

    dragging = true;
    resizer.classList.add('dragging');
    document.body.style.userSelect = 'none';
    event.preventDefault();
  });

  resizer.addEventListener('dblclick', function () {
    grid.classList.toggle('node-tree-collapsed');
    localStorage.setItem(collapsedKey, grid.classList.contains('node-tree-collapsed') ? '1' : '0');
  });

  window.addEventListener('mousemove', function (event) {
    if (!dragging) {
      return;
    }

    const rect = grid.getBoundingClientRect();
    const relativeX = event.clientX - rect.left;
    const max = Math.max(420, rect.width - 420);
    const width = Math.max(280, Math.min(max, relativeX));

    grid.style.setProperty('--tfv2-node-tree-width', width + 'px');
    localStorage.setItem(widthKey, String(width));
  });

  window.addEventListener('mouseup', function () {
    if (!dragging) {
      return;
    }

    dragging = false;
    resizer.classList.remove('dragging');
    document.body.style.userSelect = '';
  });
})();
/* PATCH 081 NODE TOOLBAR */
(function () {
  let clipboard = null;

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

  function findNodeElement(nodeId) {
    if (!window.CSS || !CSS.escape) {
      return document.querySelector('[data-node-id="' + nodeId + '"]');
    }

    return document.querySelector('[data-node-id="' + CSS.escape(nodeId) + '"]');
  }

  function parseNode(nodeId) {
    const element = findNodeElement(nodeId);

    if (!element) {
      return null;
    }

    if (element.dataset.nodeJson) {
      try {
        return JSON.parse(element.dataset.nodeJson || '{}');
      } catch (error) {
        // fallback below
      }
    }

    const strong = element.querySelector('strong');
    const small = element.querySelector('small');

    return {
      id: nodeId,
      title: strong ? strong.textContent.trim() : nodeId,
      meta: small ? small.textContent.trim() : ''
    };
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-node-action]');

    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const action = button.dataset.nodeAction;
    const nodeId = button.dataset.nodeId || '';
    const node = parseNode(nodeId);

    switch (action) {
      case 'add-child':
        toast('Kind-Node vorbereiten: ' + nodeId);
        break;

      case 'edit':
        findNodeElement(nodeId)?.click();
        toast('Node im Editor geöffnet: ' + nodeId);
        break;

      case 'copy':
        clipboard = {
          mode: 'copy',
          node: node
        };
        toast('Node kopiert: ' + nodeId);
        break;

      case 'copy-reference':
        clipboard = {
          mode: 'reference',
          node: node
        };
        toast('Referenz vorbereitet: ' + nodeId);
        break;

      case 'duplicate':
        toast('Duplizieren vorbereitet: ' + nodeId);
        break;

      case 'move':
        toast('Verschieben vorbereitet: ' + nodeId);
        break;

      case 'delete':
        toast('Löschen vorbereitet: ' + nodeId);
        break;

      default:
        toast('Aktion vorbereitet: ' + action);
    }

    window.TreeForgeV2Clipboard = clipboard;
  });
})();
/* PATCH 082 NODE ACTION MENU */
(function () {
  function ensureMenus() {
    document.querySelectorAll('.tfv2-node').forEach(node => {
      if (node.querySelector('.tfv2-node-menu')) {
        return;
      }

      const nodeId = node.dataset.nodeId || '';
      const toolbar = node.querySelector('.tfv2-node-toolbar');

      if (toolbar) {
        toolbar.remove();
      }

      const menu = document.createElement('div');
      menu.className = 'tfv2-node-menu';
      menu.innerHTML = `
        <button type="button" class="tfv2-node-menu-toggle" aria-label="Node-Menü öffnen">⋯</button>
        <div class="tfv2-node-menu-panel" hidden>
          <button type="button" data-node-action="add-child" data-node-id="${nodeId}">＋ Hinzufügen</button>
          <button type="button" data-node-action="edit" data-node-id="${nodeId}">✏ Bearbeiten</button>
          <button type="button" data-node-action="copy" data-node-id="${nodeId}">📋 Kopieren</button>
          <button type="button" data-node-action="copy-reference" data-node-id="${nodeId}">🔗 Als Referenz einfügen</button>
          <button type="button" data-node-action="duplicate" data-node-id="${nodeId}">🧬 Duplizieren</button>
          <button type="button" data-node-action="move" data-node-id="${nodeId}">↕ Verschieben</button>
          <button type="button" class="danger" data-node-action="delete" data-node-id="${nodeId}">🗑 Löschen</button>
        </div>
      `;

      node.appendChild(menu);
    });
  }

  function closeMenus(except) {
    document.querySelectorAll('.tfv2-node-menu-panel').forEach(panel => {
      if (panel !== except) {
        panel.hidden = true;
      }
    });
  }

  document.addEventListener('click', event => {
    const toggle = event.target.closest('.tfv2-node-menu-toggle');

    if (toggle) {
      event.preventDefault();
      event.stopPropagation();

      const panel = toggle.parentElement.querySelector('.tfv2-node-menu-panel');
      const willOpen = panel.hidden;

      closeMenus(panel);
      panel.hidden = !willOpen;
      return;
    }

    if (!event.target.closest('.tfv2-node-menu-panel')) {
      closeMenus();
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeMenus();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureMenus);
  } else {
    ensureMenus();
  }

  window.TreeForgeV2EnsureNodeMenus = ensureMenus;
})();
/* PATCH 083 NODE MENU UX POLISH */
(function () {
  function nodeInfo(node) {
    const strong = node.querySelector('strong');
    const small = node.querySelector('small');

    return {
      title: strong ? strong.textContent.trim() : 'Node',
      meta: small ? small.textContent.trim() : (node.dataset.nodeId || '')
    };
  }

  function rebuildMenu(menu, node) {
    if (menu.dataset.uxPolished === '1') {
      return;
    }

    const nodeId = node.dataset.nodeId || '';
    const info = nodeInfo(node);

    menu.innerHTML = `
      <button type="button" class="tfv2-node-menu-toggle" aria-label="Node-Menü öffnen">⋯</button>
      <div class="tfv2-node-menu-panel" hidden>
        <div class="tfv2-node-menu-head">
          <strong>${escapeHtml(info.title)}</strong>
          <small>${escapeHtml(info.meta)}</small>
        </div>

        <div class="tfv2-node-menu-group">
          <button type="button" data-node-action="add-child" data-node-id="${escapeHtml(nodeId)}"><span>＋</span><em>Hinzufügen</em></button>
        </div>

        <div class="tfv2-node-menu-group">
          <button type="button" data-node-action="edit" data-node-id="${escapeHtml(nodeId)}"><span>✏</span><em>Bearbeiten</em></button>
        </div>

        <div class="tfv2-node-menu-group">
          <button type="button" data-node-action="copy" data-node-id="${escapeHtml(nodeId)}"><span>📋</span><em>Kopieren</em></button>
          <button type="button" data-node-action="copy-reference" data-node-id="${escapeHtml(nodeId)}"><span>🔗</span><em>Als Referenz einfügen</em></button>
          <button type="button" data-node-action="duplicate" data-node-id="${escapeHtml(nodeId)}"><span>🧬</span><em>Duplizieren</em></button>
        </div>

        <div class="tfv2-node-menu-group">
          <button type="button" data-node-action="move" data-node-id="${escapeHtml(nodeId)}"><span>↕</span><em>Verschieben</em></button>
        </div>

        <div class="tfv2-node-menu-group">
          <button type="button" class="danger" data-node-action="delete" data-node-id="${escapeHtml(nodeId)}"><span>🗑</span><em>Löschen</em></button>
        </div>
      </div>
    `;

    menu.dataset.uxPolished = '1';
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function polishMenus() {
    document.querySelectorAll('.tfv2-node').forEach(node => {
      const menu = node.querySelector('.tfv2-node-menu');

      if (menu) {
        rebuildMenu(menu, node);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', polishMenus);
  } else {
    polishMenus();
  }

  const previousEnsure = window.TreeForgeV2EnsureNodeMenus;

  window.TreeForgeV2EnsureNodeMenus = function () {
    if (typeof previousEnsure === 'function') {
      previousEnsure();
    }

    polishMenus();
  };

  window.setTimeout(polishMenus, 200);
})();
/* PATCH 084 FULLSCREEN NODE EDITOR */
(function () {
  let sourceField = null;

  function ensureModal() {
    let modal = document.getElementById('tfv2FullscreenEditor');

    if (modal) {
      return modal;
    }

    modal = document.createElement('div');
    modal.id = 'tfv2FullscreenEditor';
    modal.className = 'tfv2-fullscreen-editor';
    modal.hidden = true;
    modal.innerHTML = `
      <div class="tfv2-fullscreen-backdrop" data-fs-close></div>
      <div class="tfv2-fullscreen-dialog" role="dialog" aria-modal="true" aria-label="Großer Node Editor">
        <header class="tfv2-fullscreen-head">
          <div>
            <strong id="tfv2FullscreenTitle">Großer Editor</strong>
            <span id="tfv2FullscreenSub">Node Inhalt bearbeiten</span>
          </div>
          <button type="button" class="tfv2-fullscreen-close" data-fs-close aria-label="Schließen">×</button>
        </header>

        <div class="tfv2-fullscreen-body">
          <textarea id="tfv2FullscreenTextarea" spellcheck="false"></textarea>
        </div>

        <footer class="tfv2-fullscreen-foot">
          <button type="button" class="tfv2-btn secondary" data-fs-close>Abbrechen</button>
          <button type="button" class="tfv2-btn" id="tfv2FullscreenApply">Übernehmen</button>
        </footer>
      </div>
    `;

    document.body.appendChild(modal);

    modal.addEventListener('click', event => {
      if (event.target.closest('[data-fs-close]')) {
        closeModal();
      }
    });

    const apply = modal.querySelector('#tfv2FullscreenApply');
    apply.addEventListener('click', () => {
      const textarea = modal.querySelector('#tfv2FullscreenTextarea');

      if (sourceField && textarea) {
        sourceField.value = textarea.value;
        sourceField.dispatchEvent(new Event('input', { bubbles: true }));
        sourceField.dispatchEvent(new Event('change', { bubbles: true }));
      }

      closeModal();
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !modal.hidden) {
        closeModal();
      }

      if ((event.ctrlKey || event.metaKey) && event.key === 'Enter' && !modal.hidden) {
        event.preventDefault();
        modal.querySelector('#tfv2FullscreenApply')?.click();
      }
    });

    return modal;
  }

  function closeModal() {
    const modal = document.getElementById('tfv2FullscreenEditor');

    if (!modal) return;

    modal.hidden = true;
    document.body.classList.remove('tfv2-fullscreen-open');
    sourceField = null;
  }

  function openModal(field) {
    if (!field) {
      alert('Kein Editorfeld gefunden.');
      return;
    }

    sourceField = field;

    const modal = ensureModal();
    const textarea = modal.querySelector('#tfv2FullscreenTextarea');
    const sub = modal.querySelector('#tfv2FullscreenSub');
    const selected = document.getElementById('tfv2SelectedNode');

    if (textarea) {
      textarea.value = field.value || '';
    }

    if (sub) {
      sub.textContent = selected ? selected.textContent : (field.id || 'Node Inhalt');
    }

    modal.hidden = false;
    document.body.classList.add('tfv2-fullscreen-open');

    window.setTimeout(() => {
      textarea?.focus();
    }, 40);
  }

  function findFallbackField() {
    const selectors = [
      '#tfv2NodeContentDom',
      '#tfv2NodeContent',
      '.tfv2-type-editor-dom textarea',
      '.tfv2-tab-panel.active textarea',
      '.tfv2-editor-panel textarea'
    ];

    for (const selector of selectors) {
      const field = document.querySelector(selector);

      if (field) {
        return field;
      }
    }

    return null;
  }

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-large-editor], #tfv2OpenLargeEditor');

    if (!trigger) {
      return;
    }

    event.preventDefault();

    let field = null;
    const selector = trigger.dataset.largeEditor;

    if (selector) {
      field = document.querySelector(selector);
    }

    if (!field) {
      field = findFallbackField();
    }

    openModal(field);
  });
})();
/* PATCH 085 CLIPBOARD ENGINE */
(function () {
  const key = 'tfv2.nodeClipboard';

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

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function readClipboard() {
    try {
      return JSON.parse(localStorage.getItem(key) || 'null');
    } catch (error) {
      return null;
    }
  }

  function writeClipboard(data) {
    localStorage.setItem(key, JSON.stringify(data));
    window.TreeForgeV2Clipboard = data;
    document.dispatchEvent(new CustomEvent('tfv2:clipboard-change', { detail: data }));
  }

  function clearClipboard() {
    localStorage.removeItem(key);
    window.TreeForgeV2Clipboard = null;
    document.dispatchEvent(new CustomEvent('tfv2:clipboard-change', { detail: null }));
  }

  function findNodeElement(nodeId) {
    if (!nodeId) return null;

    if (window.CSS && CSS.escape) {
      return document.querySelector('[data-node-id="' + CSS.escape(nodeId) + '"]');
    }

    return document.querySelector('[data-node-id="' + nodeId + '"]');
  }

  function parseNode(nodeId) {
    const el = findNodeElement(nodeId);

    if (!el) return null;

    if (el.dataset.nodeJson) {
      try {
        return JSON.parse(el.dataset.nodeJson || '{}');
      } catch (error) {
        // fallback
      }
    }

    const strong = el.querySelector('strong');
    const small = el.querySelector('small');
    const meta = small ? small.textContent.trim() : '';
    const parts = meta.split('·').map(part => part.trim());

    return {
      id: nodeId,
      title: strong ? strong.textContent.trim() : nodeId,
      type: parts[1] || 'Node',
      children: []
    };
  }

  function nodeLabel(node) {
    if (!node) return 'Node';
    return (node.title || node.type || node.id || 'Node');
  }

  function augmentMenus() {
    const clipboard = readClipboard();

    document.querySelectorAll('.tfv2-node-menu-panel').forEach(panel => {
      const menu = panel.closest('.tfv2-node-menu');
      const node = menu ? menu.closest('.tfv2-node') : null;
      const targetId = node ? (node.dataset.nodeId || '') : '';

      panel.querySelectorAll('[data-clipboard-extra="1"]').forEach(el => el.remove());

      if (!clipboard || !clipboard.node) {
        return;
      }

      const group = document.createElement('div');
      group.className = 'tfv2-node-menu-group tfv2-clipboard-group';
      group.dataset.clipboardExtra = '1';

      group.innerHTML = `
        <button type="button" data-node-action="paste-copy" data-node-id="${escapeHtml(targetId)}">
          <span>📥</span><em>Einfügen</em>
        </button>
        <button type="button" data-node-action="paste-reference" data-node-id="${escapeHtml(targetId)}">
          <span>🔗</span><em>Referenz einfügen</em>
        </button>
        <button type="button" data-node-action="clipboard-clear" data-node-id="${escapeHtml(targetId)}">
          <span>✕</span><em>Clipboard leeren</em>
        </button>
      `;

      const groups = panel.querySelectorAll('.tfv2-node-menu-group');

      if (groups.length >= 3) {
        groups[2].after(group);
      } else {
        panel.appendChild(group);
      }
    });
  }

  function markClipboardInfo() {
    const clipboard = readClipboard();

    document.querySelectorAll('.tfv2-clipboard-info').forEach(el => el.remove());

    if (!clipboard || !clipboard.node) {
      return;
    }

    const sidebar = document.querySelector('.tfv2-sidebar');

    if (!sidebar) {
      return;
    }

    const box = document.createElement('section');
    box.className = 'tfv2-side-card tfv2-clipboard-info';
    box.innerHTML = `
      <header><strong>📋 Clipboard</strong><button type="button" data-node-action="clipboard-clear">×</button></header>
      <div class="tfv2-empty">
        <strong>${escapeHtml(clipboard.mode === 'reference' ? 'Referenz' : 'Kopie')}</strong><br>
        ${escapeHtml(nodeLabel(clipboard.node))}<br>
        <small>${escapeHtml(clipboard.node.id || '')}</small>
      </div>
    `;

    sidebar.appendChild(box);
  }

  function refreshClipboardUi() {
    augmentMenus();
    markClipboardInfo();
  }

  document.addEventListener('tfv2:clipboard-change', refreshClipboardUi);

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-node-action]');

    if (!button) {
      return;
    }

    const action = button.dataset.nodeAction;
    const nodeId = button.dataset.nodeId || '';

    if (action === 'copy' || action === 'copy-reference') {
      const node = parseNode(nodeId);

      if (!node) {
        toast('Node konnte nicht gelesen werden.');
        return;
      }

      const data = {
        mode: action === 'copy-reference' ? 'reference' : 'copy',
        node: node,
        source_node_id: node.id || nodeId,
        copied_at: new Date().toISOString()
      };

      writeClipboard(data);
      toast((data.mode === 'reference' ? 'Referenz vorbereitet: ' : 'Node kopiert: ') + (node.id || nodeId));
      window.setTimeout(refreshClipboardUi, 40);
    }

    if (action === 'paste-copy') {
      const clipboard = readClipboard();

      if (!clipboard || !clipboard.node) {
        toast('Clipboard ist leer.');
        return;
      }

      toast('Einfügen vorbereitet in: ' + nodeId);
    }

    if (action === 'paste-reference') {
      const clipboard = readClipboard();

      if (!clipboard || !clipboard.node) {
        toast('Clipboard ist leer.');
        return;
      }

      toast('Referenz einfügen vorbereitet in: ' + nodeId);
    }

    if (action === 'clipboard-clear') {
      clearClipboard();
      toast('Clipboard geleert.');
    }
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshClipboardUi);
  } else {
    refreshClipboardUi();
  }

  const previousEnsure = window.TreeForgeV2EnsureNodeMenus;

  window.TreeForgeV2EnsureNodeMenus = function () {
    if (typeof previousEnsure === 'function') {
      previousEnsure();
    }

    refreshClipboardUi();
  };

  window.TreeForgeV2ClipboardApi = {
    read: readClipboard,
    write: writeClipboard,
    clear: clearClipboard,
    refresh: refreshClipboardUi
  };
})();
/* PATCH 097 HIGHLIGHT LAST ADDED NODE */
(function () {
  function highlightLastAdded() {
    const id = sessionStorage.getItem('tfv2.lastAddedNode');

    if (!id) {
      return;
    }

    const selector = window.CSS && CSS.escape
      ? '[data-node-id="' + CSS.escape(id) + '"]'
      : '[data-node-id="' + id + '"]';

    const node = document.querySelector(selector);

    if (!node) {
      return;
    }

    sessionStorage.removeItem('tfv2.lastAddedNode');

    node.classList.add('is-selected', 'active');
    node.scrollIntoView({ behavior: 'smooth', block: 'center' });

    window.setTimeout(() => {
      node.classList.remove('is-selected');
    }, 4500);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', highlightLastAdded);
  } else {
    window.setTimeout(highlightLastAdded, 150);
  }
})();
/* PATCH 101 ROBUST DELETE DUPLICATE ACTIONS */
(function () {
  function currentPage() {
    return new URLSearchParams(window.location.search).get('page') || 'home';
  }

  function currentWorkspace() {
    return new URLSearchParams(window.location.search).get('workspace') || 'draft';
  }

  function toast(message, type) {
    if (window.TreeForgeV2Mutations && typeof window.TreeForgeV2Mutations.toast === 'function') {
      window.TreeForgeV2Mutations.toast(message, type);
      return;
    }

    let box = document.getElementById('tfv2NodeActionToast');

    if (!box) {
      box = document.createElement('div');
      box.id = 'tfv2NodeActionToast';
      box.className = 'tfv2-node-action-toast';
      document.body.appendChild(box);
    }

    box.textContent = message;
    box.classList.toggle('error', type === 'error');
    box.classList.add('show');

    window.setTimeout(() => {
      box.classList.remove('show');
      box.classList.remove('error');
    }, 4500);
  }

  function selectorForNode(nodeId) {
    return window.CSS && CSS.escape
      ? '[data-node-id="' + CSS.escape(nodeId) + '"]'
      : '[data-node-id="' + nodeId + '"]';
  }

  function nodeTitle(nodeId) {
    const node = document.querySelector(selectorForNode(nodeId));

    if (!node) {
      return nodeId;
    }

    const strong = node.querySelector('strong');

    return strong ? strong.textContent.trim() : nodeId;
  }

  async function mutate(action, payload) {
    const response = await fetch('/api/explorer-v2/mutate.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        page: currentPage(),
        workspace: currentWorkspace(),
        action: action,
        payload: payload || {}
      })
    });

    const json = await response.json().catch(() => null);

    if (!response.ok || !json || json.ok !== true) {
      throw new Error((json && json.error) ? json.error : 'Mutation fehlgeschlagen.');
    }

    return json;
  }

  function reloadDraft(delay) {
    localStorage.removeItem('tfv2.collapsedNodes.' + currentPage());

    const url = new URL(window.location.href);
    url.searchParams.set('page', currentPage());
    url.searchParams.set('workspace', 'draft');
    url.searchParams.set('_', String(Date.now()));

    window.setTimeout(() => {
      window.location.href = url.toString();
    }, delay || 1800);
  }

  async function handleDelete(button, nodeId) {
    const title = nodeTitle(nodeId);

    if (!confirm('Node wirklich löschen?\n\n' + title + '\n' + nodeId)) {
      return;
    }

    button.disabled = true;

    try {
      await mutate('delete', { node_id: nodeId });
      toast('Node gelöscht: ' + title);
      reloadDraft(1800);
    } catch (error) {
      button.disabled = false;
      toast(error.message || 'Node konnte nicht gelöscht werden.', 'error');
    }
  }

  async function handleDuplicate(button, nodeId) {
    const title = nodeTitle(nodeId);

    button.disabled = true;

    try {
      const json = await mutate('duplicate', { node_id: nodeId });
      const newNodeId = json && json.result && json.result.node_id ? json.result.node_id : '';

      if (newNodeId) {
        sessionStorage.setItem('tfv2.lastAddedNode', newNodeId);
      }

      toast('Node dupliziert: ' + title);
      reloadDraft(1800);
    } catch (error) {
      button.disabled = false;
      toast(error.message || 'Node konnte nicht dupliziert werden.', 'error');
    }
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-node-action="delete"], [data-node-action="duplicate"]');

    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    const action = button.dataset.nodeAction;
    const nodeId = button.dataset.nodeId || '';

    if (!nodeId) {
      toast('Node-ID fehlt.', 'error');
      return;
    }

    if (action === 'delete') {
      handleDelete(button, nodeId);
      return;
    }

    if (action === 'duplicate') {
      handleDuplicate(button, nodeId);
    }
  }, true);
})();
/* PATCH 103 CLIPBOARD PASTE API INTEGRATION */
(function () {
  const clipboardKey = 'tfv2.nodeClipboard';

  function currentPage() {
    return new URLSearchParams(window.location.search).get('page') || 'home';
  }

  function currentWorkspace() {
    return new URLSearchParams(window.location.search).get('workspace') || 'draft';
  }

  function toast(message, type) {
    if (window.TreeForgeV2Mutations && typeof window.TreeForgeV2Mutations.toast === 'function') {
      window.TreeForgeV2Mutations.toast(message, type);
      return;
    }

    let box = document.getElementById('tfv2NodeActionToast');

    if (!box) {
      box = document.createElement('div');
      box.id = 'tfv2NodeActionToast';
      box.className = 'tfv2-node-action-toast';
      document.body.appendChild(box);
    }

    box.textContent = message;
    box.classList.toggle('error', type === 'error');
    box.classList.add('show');

    window.setTimeout(() => {
      box.classList.remove('show');
      box.classList.remove('error');
    }, 4500);
  }

  function readClipboard() {
    if (window.TreeForgeV2ClipboardApi && typeof window.TreeForgeV2ClipboardApi.read === 'function') {
      return window.TreeForgeV2ClipboardApi.read();
    }

    try {
      return JSON.parse(localStorage.getItem(clipboardKey) || 'null');
    } catch (error) {
      return null;
    }
  }

  function clearClipboard() {
    if (window.TreeForgeV2ClipboardApi && typeof window.TreeForgeV2ClipboardApi.clear === 'function') {
      window.TreeForgeV2ClipboardApi.clear();
      return;
    }

    localStorage.removeItem(clipboardKey);
    window.TreeForgeV2Clipboard = null;
  }

  async function mutate(action, payload) {
    const response = await fetch('/api/explorer-v2/mutate.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        page: currentPage(),
        workspace: currentWorkspace(),
        action: action,
        payload: payload || {}
      })
    });

    const json = await response.json().catch(() => null);

    if (!response.ok || !json || json.ok !== true) {
      throw new Error((json && json.error) ? json.error : 'Mutation fehlgeschlagen.');
    }

    return json;
  }

  function reloadDraft(delay) {
    localStorage.removeItem('tfv2.collapsedNodes.' + currentPage());

    const url = new URL(window.location.href);
    url.searchParams.set('page', currentPage());
    url.searchParams.set('workspace', 'draft');
    url.searchParams.set('_', String(Date.now()));

    window.setTimeout(() => {
      window.location.href = url.toString();
    }, delay || 1800);
  }

  async function pasteCopy(button, parentId) {
    const clipboard = readClipboard();

    if (!clipboard || !clipboard.node) {
      toast('Clipboard ist leer.', 'error');
      return;
    }

    button.disabled = true;

    try {
      const json = await mutate('paste-copy', {
        parent_id: parentId,
        node: clipboard.node,
        source_node_id: clipboard.source_node_id || clipboard.node.id || ''
      });

      const newNodeId = json && json.result && json.result.node_id ? json.result.node_id : '';

      if (newNodeId) {
        sessionStorage.setItem('tfv2.lastAddedNode', newNodeId);
      }

      toast('Node eingefügt.');
      reloadDraft(1800);
    } catch (error) {
      button.disabled = false;
      toast(error.message || 'Node konnte nicht eingefügt werden.', 'error');
    }
  }

  async function pasteReference(button, parentId) {
    const clipboard = readClipboard();

    if (!clipboard || !clipboard.node) {
      toast('Clipboard ist leer.', 'error');
      return;
    }

    const sourceNodeId = clipboard.source_node_id || clipboard.node.id || '';

    if (!sourceNodeId) {
      toast('Source-Node fehlt im Clipboard.', 'error');
      return;
    }

    button.disabled = true;

    try {
      const json = await mutate('paste-reference', {
        parent_id: parentId,
        source_node_id: sourceNodeId,
        node: clipboard.node
      });

      const newNodeId = json && json.result && json.result.node_id ? json.result.node_id : '';

      if (newNodeId) {
        sessionStorage.setItem('tfv2.lastAddedNode', newNodeId);
      }

      toast('Referenz eingefügt.');
      reloadDraft(1800);
    } catch (error) {
      button.disabled = false;
      toast(error.message || 'Referenz konnte nicht eingefügt werden.', 'error');
    }
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-node-action="paste-copy"], [data-node-action="paste-reference"], [data-node-action="clipboard-clear"]');

    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    const action = button.dataset.nodeAction;
    const parentId = button.dataset.nodeId || '';

    if (action === 'clipboard-clear') {
      clearClipboard();
      toast('Clipboard geleert.');
      return;
    }

    if (!parentId) {
      toast('Ziel-Node fehlt.', 'error');
      return;
    }

    if (action === 'paste-copy') {
      pasteCopy(button, parentId);
      return;
    }

    if (action === 'paste-reference') {
      pasteReference(button, parentId);
    }
  }, true);
})();



