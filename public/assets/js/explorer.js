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

      idTarget.textContent = data.id || '–';
      typeTarget.textContent = data.type || 'unknown';
      childrenTarget.textContent = data.children_count ?? 0;

      renderPreview(data.preview || {});
      renderProperties(data.properties || {});
      jsonTarget.textContent = JSON.stringify(data.raw || data, null, 2);

      empty.hidden = true;
      content.hidden = false;
    });
  });
})();