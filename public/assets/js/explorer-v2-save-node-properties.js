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

  function activeNodeElement() {
    return document.querySelector('.tfv2-node.active, .tfv2-node.is-selected') || document.querySelector('.tfv2-node');
  }

  function parseNode(element) {
    if (!element) return {};

    if (element.dataset.nodeJson) {
      try {
        return JSON.parse(element.dataset.nodeJson || '{}') || {};
      } catch (error) {
        // fallback below
      }
    }

    const strong = element.querySelector('strong');
    const small = element.querySelector('small');
    const meta = small ? small.textContent.trim() : '';
    const parts = meta.split('·').map(part => part.trim());

    return {
      id: element.dataset.nodeId || parts[0] || '',
      type: parts[1] || 'Node',
      title: strong ? strong.textContent.trim() : 'Node',
      properties: {}
    };
  }

  function nodeType(node) {
    return String(node.type || '').toLowerCase();
  }

  function setDeep(target, path, value) {
    const parts = String(path || '').split('.').filter(Boolean);

    if (parts.length === 0) return;

    let current = target;

    for (let i = 0; i < parts.length - 1; i++) {
      const part = parts[i];

      if (!current[part] || typeof current[part] !== 'object' || Array.isArray(current[part])) {
        current[part] = {};
      }

      current = current[part];
    }

    const key = parts[parts.length - 1];

    if (key === 'days' && typeof value === 'string') {
      current[key] = value.split(',').map(item => item.trim()).filter(Boolean);
      return;
    }

    current[key] = value;
  }

  function valueOf(field) {
    if (!field) return '';

    if (field.type === 'checkbox') {
      return field.checked;
    }

    if (field.type === 'number') {
      return field.value === '' ? '' : Number(field.value);
    }

    return field.value;
  }

  function ensureGroups(properties) {
    properties.content = properties.content && typeof properties.content === 'object' && !Array.isArray(properties.content) ? properties.content : {};
    properties.layout = properties.layout && typeof properties.layout === 'object' && !Array.isArray(properties.layout) ? properties.layout : {};
    properties.spacing = properties.spacing && typeof properties.spacing === 'object' && !Array.isArray(properties.spacing) ? properties.spacing : {};
    properties.design = properties.design && typeof properties.design === 'object' && !Array.isArray(properties.design) ? properties.design : {};
    properties.behavior = properties.behavior && typeof properties.behavior === 'object' && !Array.isArray(properties.behavior) ? properties.behavior : {};
    properties.advanced = properties.advanced && typeof properties.advanced === 'object' && !Array.isArray(properties.advanced) ? properties.advanced : {};
    properties.visibility = properties.visibility && typeof properties.visibility === 'object' && !Array.isArray(properties.visibility) ? properties.visibility : {};
    properties.responsive = properties.responsive && typeof properties.responsive === 'object' && !Array.isArray(properties.responsive) ? properties.responsive : {};
    return properties;
  }

  function collectPropertyEditor(editor) {
    const base = {};
    const properties = {};

    editor.querySelectorAll('[data-node-base]').forEach(field => {
      const key = field.dataset.nodeBase;
      if (key) base[key] = valueOf(field);
    });

    editor.querySelectorAll('[data-node-property]').forEach(field => {
      const path = field.dataset.nodeProperty;
      if (!path) return;

      if (path === 'custom_css') {
        properties.custom_css = valueOf(field);
        return;
      }

      setDeep(properties, path, valueOf(field));
    });

    return { base, properties: ensureGroups(properties) };
  }

  function readById(id) {
    const field = document.getElementById(id);
    return field ? valueOf(field) : '';
  }

  function collectTypeEditor(node) {
    const base = {};
    const properties = ensureGroups({});
    const type = nodeType(node);

    const title = readById('tfv2NodeTitleDom');
    if (title !== '') base.title = title;

    if (type.includes('text')) {
      const value = readById('tfv2NodeContentDom');
      properties.content.text = value;
      properties.content.content = value;
      return { base, properties };
    }

    if (type.includes('markdown')) {
      const value = readById('tfv2NodeContentDom');
      properties.content.markdown = value;
      properties.content.content = value;
      return { base, properties };
    }

    if (type.includes('css')) {
      const value = readById('tfv2NodeContentDom');
      properties.content.css = value;
      properties.content.content = value;
      return { base, properties };
    }

    if (type.includes('html')) {
      const value = readById('tfv2NodeContentDom');
      properties.content.html = value;
      properties.content.content = value;
      return { base, properties };
    }

    if (type.includes('image')) {
      const media = readById('tfv2ImageMediaIdDom');
      properties.content.media_id = media;
      properties.content.src = media;
      properties.content.alt = readById('tfv2ImageAltDom');
      properties.content.caption = readById('tfv2ImageCaptionDom');
      properties.behavior.zoom = readById('tfv2ImageZoomDom');
      properties.behavior.url = readById('tfv2ImageLinkUrlDom');
      properties.behavior.target = readById('tfv2ImageLinkTargetDom') || '_self';
      return { base, properties };
    }

    if (type.includes('button')) {
      properties.content.label = readById('tfv2ButtonLabelDom');
      properties.behavior.url = readById('tfv2ButtonUrlDom');
      properties.behavior.target = readById('tfv2ButtonTargetDom') || '_self';
      return { base, properties };
    }

    if (type.includes('columns')) {
      const columns = readById('tfv2ColumnsCountDom');
      const gap = readById('tfv2ColumnsGapDom');
      properties.layout.columns = columns === '' ? '' : Number(columns);
      properties.spacing.gap = gap;
      properties.advanced.settings = {
        columns: columns === '' ? '' : Number(columns),
        gap: gap
      };
      return { base, properties };
    }

    const value = readById('tfv2NodeContentDom');
    if (value !== '') {
      properties.content.content = value;
    }

    return { base, properties };
  }

  function normalizeContentAliases(node, payload) {
    const type = nodeType(node);
    payload.properties = ensureGroups(payload.properties || {});
    const content = payload.properties.content;

    if (type.includes('text')) {
      const value = typeof content.text !== 'undefined' ? content.text : (typeof content.content !== 'undefined' ? content.content : undefined);
      if (typeof value !== 'undefined') {
        content.text = value;
        content.content = value;
      }
    }

    if (type.includes('markdown')) {
      const value = typeof content.markdown !== 'undefined' ? content.markdown : (typeof content.content !== 'undefined' ? content.content : undefined);
      if (typeof value !== 'undefined') {
        content.markdown = value;
        content.content = value;
      }
    }

    if (type.includes('css')) {
      const value = typeof content.css !== 'undefined' ? content.css : (typeof content.content !== 'undefined' ? content.content : undefined);
      if (typeof value !== 'undefined') {
        content.css = value;
        content.content = value;
      }
    }

    if (type.includes('html')) {
      const value = typeof content.html !== 'undefined' ? content.html : (typeof content.content !== 'undefined' ? content.content : undefined);
      if (typeof value !== 'undefined') {
        content.html = value;
        content.content = value;
      }
    }

    if (type.includes('image')) {
      const src = typeof content.media_id !== 'undefined' ? content.media_id : (typeof content.src !== 'undefined' ? content.src : undefined);
      if (typeof src !== 'undefined') {
        content.media_id = src;
        content.src = src;
      }
    }

    return payload;
  }

  function collectPayload(node) {
    const propertyEditor = document.querySelector('.tfv2-property-editor');
    let payload;

    if (propertyEditor) {
      payload = collectPropertyEditor(propertyEditor);
    } else {
      payload = collectTypeEditor(node);
    }

    return normalizeContentAliases(node, payload);
  }

  async function updateNode(nodeId, payload) {
    const response = await fetch('/api/explorer-v2/mutate.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        page: currentPage(),
        workspace: currentWorkspace(),
        action: 'update-node',
        payload: {
          node_id: nodeId,
          base: payload.base || {},
          properties: payload.properties || {}
        }
      })
    });

    const text = await response.text();
    let json = null;

    try {
      json = JSON.parse(text);
    } catch (error) {
      throw new Error('API lieferte kein JSON: ' + text.slice(0, 300));
    }

    if (!response.ok || !json || json.ok !== true) {
      throw new Error((json && json.error) ? json.error : 'Eigenschaften konnten nicht gespeichert werden.');
    }

    return json;
  }

  function setDirty(editor, dirty) {
    if (!editor) return;

    editor.classList.toggle('is-dirty', dirty);

    editor.querySelectorAll('[data-node-save], .tfv2-property-save').forEach(button => {
      button.disabled = !dirty;
      button.textContent = 'Eigenschaften übernehmen';
    });
  }

  document.addEventListener('input', function (event) {
    const editor = event.target.closest('.tfv2-property-editor');
    if (editor) setDirty(editor, true);
  }, true);

  document.addEventListener('change', function (event) {
    const editor = event.target.closest('.tfv2-property-editor');
    if (editor) setDirty(editor, true);
  }, true);

  function isSaveButton(target) {
    const button = target.closest('[data-node-save], .tfv2-property-save, .tfv2-editor-panel footer .tfv2-btn:not(.secondary), .tfv2-head-actions button.tfv2-btn');
    if (!button) return null;

    const text = String(button.textContent || '').toLowerCase();
    if (button.matches('[data-node-save], .tfv2-property-save')) return button;
    if (text.includes('speichern') || text.includes('übernehmen')) return button;

    return null;
  }

  document.addEventListener('click', async function (event) {
    const button = isSaveButton(event.target);
    if (!button) return;

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    const nodeElement = activeNodeElement();
    const node = parseNode(nodeElement);
    const propertyEditor = document.querySelector('.tfv2-property-editor');
    const nodeId = (propertyEditor && propertyEditor.dataset.currentNodeId) || node.id || '';

    if (!nodeId) {
      toast('Node-ID fehlt. Bitte zuerst eine Node anklicken.', 'error');
      return;
    }

    const payload = collectPayload(node);

    document.querySelectorAll('[data-node-save], .tfv2-property-save').forEach(btn => {
      btn.disabled = true;
      btn.textContent = 'Speichert...';
    });
    button.disabled = true;
    button.textContent = 'Speichert...';

    try {
      const result = await updateNode(nodeId, payload);

      toast('Eigenschaften gespeichert.');
      if (propertyEditor) setDirty(propertyEditor, false);
      sessionStorage.setItem('tfv2.lastAddedNode', nodeId);

      const url = new URL(window.location.href);
      url.searchParams.set('page', currentPage());
      url.searchParams.set('workspace', currentWorkspace());
      url.searchParams.set('_', String(Date.now()));

      window.setTimeout(() => {
        window.location.href = url.toString();
      }, 350);
    } catch (error) {
      toast(error.message || 'Eigenschaften konnten nicht gespeichert werden.', 'error');
      if (propertyEditor) setDirty(propertyEditor, true);
      button.disabled = false;
      button.textContent = 'Eigenschaften übernehmen';
    }
  }, true);

  document.addEventListener('click', function (event) {
    const cancel = event.target.closest('[data-property-cancel]');
    if (!cancel) return;

    event.preventDefault();

    const active = activeNodeElement();
    if (active && window.TreeForgeV2PropertyEditor) {
      window.TreeForgeV2PropertyEditor.render(window.TreeForgeV2PropertyEditor.parseNode(active));
    }
  }, true);
})();