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
  const markdownPreview = document.getElementById('tfMarkdownPreview');
  const markdownEditorSection = document.getElementById('tfMarkdownEditorSection');
  const markdownEditor = document.getElementById('tfMarkdownEditor');
  const saveMarkdownButton = document.getElementById('tfSaveMarkdownNode');
  const markdownSaveStatus = document.getElementById('tfMarkdownSaveStatus');
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
    if (markdownPreview) {
      markdownPreview.hidden = true;
      markdownPreview.innerHTML = '';
    }

    previewCode.textContent = '';
    previewCode.className = '';
    previewCode.parentElement.hidden = true;
    previewSection.hidden = true;

    if (!preview || !preview.kind || preview.kind === 'none') {
      return;
    }

    if (preview.kind === 'markdown') {
      if (markdownPreview) {
        markdownPreview.innerHTML = preview.html || '';
        markdownPreview.hidden = false;
      }

      previewSection.hidden = false;
      return;
    }

    if (preview.kind === 'code') {
      const lang = preview.language || 'markup';
      previewCode.textContent = preview.content || '';
      previewCode.className = 'language-' + lang;
      previewCode.parentElement.hidden = false;
      previewSection.hidden = false;

      if (window.Prism) {
        Prism.highlightElement(previewCode);
      }
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

  function renderMarkdownEditor(data) {
    const isMarkdown = data && data.type === 'markdown';
    const workspace = (window.TreeForgeExplorer && window.TreeForgeExplorer.workspace) || 'published';
    const archive = (window.TreeForgeExplorer && window.TreeForgeExplorer.archive) || '';

    if (!markdownEditorSection || !markdownEditor || !saveMarkdownButton) {
      return;
    }

    if (!isMarkdown || archive !== '') {
      markdownEditorSection.hidden = true;
      return;
    }

    markdownEditor.value = (data.properties && data.properties.content) ? data.properties.content : '';
    markdownEditorSection.hidden = false;

    if (workspace === 'draft') {
      saveMarkdownButton.disabled = false;
      inspectorMode.textContent = 'editable';
      markdownSaveStatus.textContent = '';
    } else {
      saveMarkdownButton.disabled = true;
      markdownSaveStatus.textContent = 'Zum Bearbeiten Draft Workspace öffnen.';
    }
  }
  function renderInspector(data, keepTextFocus) {
    selectedNode = data;

    idTarget.textContent = data.id || '–';
    typeTarget.textContent = data.type || 'unknown';
    childrenTarget.textContent = data.children_count ?? 0;

    renderMarkdownEditor(data);
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


  if (saveMarkdownButton) {
    saveMarkdownButton.addEventListener('click', async () => {
      if (!selectedNode || selectedNode.type !== 'markdown') {
        return;
      }

      const oldText = saveMarkdownButton.textContent;
      saveMarkdownButton.disabled = true;
      saveMarkdownButton.textContent = 'Speichere ...';
      markdownSaveStatus.textContent = 'Speichere im Draft ...';

      try {
        const response = await fetch('/api/node/save-markdown.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            page: (window.TreeForgeExplorer && window.TreeForgeExplorer.page) || 'home',
            node: selectedNode.id,
            content: markdownEditor.value
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

        markdownSaveStatus.textContent = 'Gespeichert.';
        showNotice('success', result.message || 'Gespeichert.');
        markdownEditor.focus();

      } catch (error) {
        markdownSaveStatus.textContent = error.message;
        showNotice('error', error.message);
      } finally {
        saveMarkdownButton.disabled = false;
        saveMarkdownButton.textContent = oldText;
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
        }, 1500);

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
      const isColumns = typeSelect.value === 'columns';
      columnsOptions.hidden = !isColumns;
      columnsOptions.style.display = isColumns ? 'grid' : 'none';
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
        }, 1450);

      } catch (error) {
        showNotice('error', error.message);
      } finally {
        createButton.disabled = false;
        createButton.textContent = oldText;
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNodeWizard);
  } else {
    initNodeWizard();
  }
})();