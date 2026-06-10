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

    if (!isText) {
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

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      buttons.forEach((item) => item.classList.remove('active'));
      button.classList.add('active');

      const raw = button.getAttribute('data-node-json') || '{}';
      let data;

      try {
        data = JSON.parse(raw);
      } catch (error) {
        data = { id: '–', type: 'unknown', properties: {}, preview: {}, children_count: 0, raw: raw };
      }

      selectedNode = data;

      idTarget.textContent = data.id || '–';
      typeTarget.textContent = data.type || 'unknown';
      childrenTarget.textContent = data.children_count ?? 0;

      renderTextEditor(data);
      renderPreview(data.preview || {});
      renderProperties(data.properties || {});
      jsonTarget.textContent = JSON.stringify(data.raw || data, null, 2);

      empty.hidden = true;
      content.hidden = false;
    });
  });

  if (saveButton) {
    saveButton.addEventListener('click', async () => {
      if (!selectedNode || selectedNode.type !== 'text') {
        return;
      }

      saveStatus.textContent = 'Speichere ...';

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

        const result = await response.json();

        if (!result.ok) {
          throw new Error(result.error || 'Fehler beim Speichern');
        }

        saveStatus.textContent = 'Gespeichert. Seite wird neu geladen ...';

        setTimeout(() => {
          window.location.href = '/explorer?workspace=draft';
        }, 650);

      } catch (error) {
        saveStatus.textContent = error.message;
      }
    });
  }

  document.querySelectorAll('[data-workflow-action]').forEach((button) => {
    button.addEventListener('click', async () => {
      const action = button.getAttribute('data-workflow-action');

      if (!action) {
        return;
      }

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

        if (!result.ok) {
          throw new Error(result.error || 'Workflow Fehler');
        }

        window.location.href = '/explorer?workspace=' + encodeURIComponent(result.target);

      } catch (error) {
        button.disabled = false;
        button.textContent = oldText;
        alert(error.message);
      }
    });
  });
})();