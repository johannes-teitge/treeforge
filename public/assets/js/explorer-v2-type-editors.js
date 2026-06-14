(function () {
  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function parseNode(element) {
    if (!element) return {};

    if (element.dataset.nodeJson) {
      try {
        return JSON.parse(element.dataset.nodeJson || '{}') || {};
      } catch (error) {
        // continue with fallback
      }
    }

    const strong = element.querySelector('strong');
    const small = element.querySelector('small');

    const title = strong ? strong.textContent.trim() : 'Node';
    const meta = small ? small.textContent.trim() : '';
    const parts = meta.split('·').map(part => part.trim());

    return {
      id: element.dataset.nodeId || parts[0] || title.toLowerCase().replace(/\s+/g, '-'),
      type: parts[1] || title,
      title: title,
      children: []
    };
  }

  function getType(node) {
    return String(node.type || node.title || '').toLowerCase();
  }

  function pick(node, keys, fallback) {
    for (const key of keys) {
      if (typeof node[key] !== 'undefined' && node[key] !== null) {
        return node[key];
      }
    }
    return fallback ?? '';
  }

  function editorPanel() {
    let panel = document.querySelector('.tfv2-tab-panel[data-panel="editor"]');

    if (!panel) {
      panel = document.querySelector('.tfv2-editor-panel .tfv2-editor');
    }

    if (!panel) {
      panel = document.querySelector('.tfv2-editor-panel');
    }

    return panel;
  }

  function selectedLabel() {
    let el = document.getElementById('tfv2SelectedNode');

    if (!el) {
      const header = document.querySelector('.tfv2-editor-panel header span');
      el = header || null;
    }

    return el;
  }

  function setJson(node) {
    const pre = document.getElementById('tfv2NodeJsonPre')
      || document.querySelector('.tfv2-tab-panel[data-panel="json"] pre')
      || document.querySelector('.tfv2-editor-panel pre');

    if (pre) {
      pre.textContent = JSON.stringify(node, null, 2);
    }
  }

  function baseFields(node) {
    return `
      <div class="tfv2-dom-summary">
        <div><span>Node ID</span><strong>${escapeHtml(node.id || '–')}</strong></div>
        <div><span>Type</span><strong>${escapeHtml(node.type || 'Node')}</strong></div>
        <div><span>Children</span><strong>${Array.isArray(node.children) ? node.children.length : 0}</strong></div>
      </div>

      <label>
        Node Titel
        <input id="tfv2NodeTitleDom" value="${escapeHtml(node.title || node.label || node.type || '')}">
      </label>

      <label>
        Node ID
        <input id="tfv2NodeIdDom" value="${escapeHtml(node.id || '')}" readonly>
      </label>

      <label>
        Node Type
        <input id="tfv2NodeTypeDom" value="${escapeHtml(node.type || '')}" readonly>
      </label>
    `;
  }

  function renderCodeBlock(node) {
    const content = (node.properties && node.properties.content) ? node.properties.content : {};
    const value = String(content.code ?? node.code ?? node.content ?? node.text ?? '');
    const language = String(content.language ?? node.language ?? 'php').toLowerCase();
    const languages = ['plaintext', 'php', 'html', 'css', 'javascript', 'json', 'sql', 'bash', 'ini', 'yaml', 'xml', 'delphi'];

    return `
      ${baseFields(node)}
      <label>
        Sprache
        <select id="tfv2CodeLanguageDom">
          ${languages.map(item => `<option value="${item}"${language === item ? ' selected' : ''}>${item}</option>`).join('')}
        </select>
        <small>Nur für Darstellung/Highlighter, der Code wird nicht ausgeführt.</small>
      </label>
      <label>
        Code
        <textarea id="tfv2NodeContentDom" class="code" rows="14">${escapeHtml(value)}</textarea>
      </label>
      <button class="tfv2-btn secondary" type="button" data-large-editor="#tfv2NodeContentDom">⛶ Groß öffnen</button>
    `;
  }

  function renderHeading(node) {
    const content = (node.properties && node.properties.content) ? node.properties.content : {};
    const value = String(content.text ?? node.text ?? node.content ?? node.title ?? '');
    const level = String(content.level ?? node.level ?? 'h2').toLowerCase();
    const levels = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    return `
      ${baseFields(node)}
      <label>
        Überschrift
        <input id="tfv2HeadingTextDom" value="${escapeHtml(value)}">
        <small>Sichtbare Überschrift dieser Node.</small>
      </label>
      <label>
        Ebene
        <select id="tfv2HeadingLevelDom">
          ${levels.map(item => `<option value="${item}"${level === item ? ' selected' : ''}>${item.toUpperCase()}</option>`).join('')}
        </select>
        <small>Für eine Seite normalerweise nur eine H1 verwenden.</small>
      </label>
    `;
  }

  function renderText(node) {
    const value = pick(node, ['content', 'text', 'value'], '');

    return `
      ${baseFields(node)}
      <label>
        Text
        <textarea id="tfv2NodeContentDom" rows="12">${escapeHtml(value)}</textarea>
        <small>Einfacher Textinhalt dieser Node.</small>
      </label>
      <button class="tfv2-btn secondary" type="button" data-large-editor="#tfv2NodeContentDom">⛶ Groß öffnen</button>
    `;
  }

  function renderMarkdown(node) {
    const value = pick(node, ['markdown', 'content', 'text', 'value'], '');

    return `
      ${baseFields(node)}
      <label>
        Markdown
        <textarea id="tfv2NodeContentDom" class="code" rows="14">${escapeHtml(value)}</textarea>
        <small>Markdown wird später serverseitig gerendert.</small>
      </label>
      <button class="tfv2-btn secondary" type="button" data-large-editor="#tfv2NodeContentDom">⛶ Groß öffnen</button>
    `;
  }

  function renderCss(node) {
    const value = pick(node, ['css', 'content', 'value'], '');

    return `
      ${baseFields(node)}
      <label>
        CSS
        <textarea id="tfv2NodeContentDom" class="code" rows="14">${escapeHtml(value)}</textarea>
        <small>CSS wird später validiert und begrenzt.</small>
      </label>
      <button class="tfv2-btn secondary" type="button" data-large-editor="#tfv2NodeContentDom">⛶ Groß öffnen</button>
    `;
  }

  function renderImage(node) {
    const mediaId = pick(node, ['media_id', 'mediaId', 'image', 'src', 'value'], '');
    const alt = pick(node, ['alt', 'alt_text', 'altText'], '');
    const caption = pick(node, ['caption'], '');
    const display = pick(node, ['display', 'size'], 'content');
    const linkUrl = pick(node, ['link_url', 'linkUrl', 'url', 'href'], '');
    const linkTarget = pick(node, ['link_target', 'linkTarget', 'target'], '_self');

    return `
      ${baseFields(node)}

      <div class="tfv2-media-preview" id="tfv2ImagePreviewDom">Bildvorschau später</div>

      <label>
        Media ID / Pfad
        <div class="tfv2-inline-field">
          <input id="tfv2ImageMediaIdDom" value="${escapeHtml(mediaId)}">
          <button type="button" class="tfv2-btn secondary" id="tfv2PickMediaDom">Medien wählen</button>
        </div>
        <small>Später direkt mit der Media Library verbunden.</small>
      </label>

      <div class="tfv2-field-grid">
        <label>
          Darstellung
          <select id="tfv2ImageDisplayDom">
            <option value="content"${display === 'content' ? ' selected' : ''}>Content</option>
            <option value="hero"${display === 'hero' ? ' selected' : ''}>Hero</option>
            <option value="large"${display === 'large' ? ' selected' : ''}>Large</option>
            <option value="social"${display === 'social' ? ' selected' : ''}>Social</option>
          </select>
        </label>

        <label>
          Zoom
          <select id="tfv2ImageZoomDom">
            <option value="">Nein</option>
            <option value="lightbox">Lightbox</option>
            <option value="bump">Bump</option>
          </select>
        </label>
      </div>

      <label>
        Alt-Text
        <input id="tfv2ImageAltDom" value="${escapeHtml(alt)}">
      </label>

      <label>
        Caption
        <textarea id="tfv2ImageCaptionDom" rows="4">${escapeHtml(caption)}</textarea>
      </label>

      <div class="tfv2-field-grid">
        <label>
          Ziel-URL
          <input id="tfv2ImageLinkUrlDom" value="${escapeHtml(linkUrl)}" placeholder="optional, z. B. /kontakt">
          <small>Wenn gesetzt, wird das Bild später klickbar.</small>
        </label>

        <label>
          Target
          <select id="tfv2ImageLinkTargetDom">
            <option value="_self"${linkTarget === '_self' ? ' selected' : ''}>Gleiches Fenster</option>
            <option value="_blank"${linkTarget === '_blank' ? ' selected' : ''}>Neues Fenster</option>
          </select>
        </label>
      </div>
    `;
  }

  function renderButton(node) {
    const label = pick(node, ['label', 'text', 'title'], '');
    const url = pick(node, ['url', 'href', 'link'], '');
    const target = pick(node, ['target'], '_self');

    return `
      ${baseFields(node)}
      <div class="tfv2-field-grid">
        <label>
          Button Text
          <input id="tfv2ButtonLabelDom" value="${escapeHtml(label)}">
        </label>
        <label>
          Ziel
          <select id="tfv2ButtonTargetDom">
            <option value="_self"${target === '_self' ? ' selected' : ''}>Gleiches Fenster</option>
            <option value="_blank"${target === '_blank' ? ' selected' : ''}>Neues Fenster</option>
          </select>
        </label>
      </div>
      <label>
        URL
        <input id="tfv2ButtonUrlDom" value="${escapeHtml(url)}">
      </label>
    `;
  }

  function renderColumns(node) {
    const count = pick(node, ['columns', 'count'], Array.isArray(node.children) ? node.children.length : 2);
    const gap = pick(node, ['gap'], '1rem');

    return `
      ${baseFields(node)}
      <div class="tfv2-field-grid">
        <label>
          Spalten
          <select id="tfv2ColumnsCountDom">
            ${[2,3,4,5,6].map(n => `<option value="${n}"${Number(count) === n ? ' selected' : ''}>${n}</option>`).join('')}
          </select>
        </label>
        <label>
          Gap
          <input id="tfv2ColumnsGapDom" value="${escapeHtml(gap)}">
        </label>
      </div>
      <div class="tfv2-empty">Children werden im Node Tree gepflegt.</div>
    `;
  }

  function renderDefault(node) {
    const value = pick(node, ['content', 'text', 'value', 'html'], '');

    return `
      ${baseFields(node)}
      <label>
        Content / Value
        <textarea id="tfv2NodeContentDom" rows="10">${escapeHtml(value)}</textarea>
      </label>
      <button class="tfv2-btn secondary" type="button" data-large-editor="#tfv2NodeContentDom">⛶ Groß öffnen</button>
    `;
  }

  function renderEditor(node) {
    const panel = editorPanel();

    if (!panel) return;

    const type = getType(node);
    let html;

    if (type.includes('codeblock') || type.includes('codesnippet') || type.includes('snippet')) {
      html = renderCodeBlock(node);
    } else if (type.includes('heading') || type.includes('headline')) {
      html = renderHeading(node);
    } else if (type.includes('markdown')) {
      html = renderMarkdown(node);
    } else if (type.includes('image')) {
      html = renderImage(node);
    } else if (type.includes('button')) {
      html = renderButton(node);
    } else if (type.includes('columns') || type === 'column') {
      html = renderColumns(node);
    } else if (type.includes('css')) {
      html = renderCss(node);
    } else if (type.includes('text')) {
      html = renderText(node);
    } else {
      html = renderDefault(node);
    }

    panel.innerHTML = '<div class="tfv2-type-editor-dom">' + html + '</div>';

    const label = selectedLabel();
    if (label) {
      label.textContent = (node.type || 'Node') + ' · ' + (node.id || '');
    }

    setJson(node);
    bindMediaPicker();
  }

  function bindMediaPicker() {
    const button = document.getElementById('tfv2PickMediaDom');
    const input = document.getElementById('tfv2ImageMediaIdDom');
    const preview = document.getElementById('tfv2ImagePreviewDom');

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

  function selectNode(element) {
    const node = parseNode(element);

    document.querySelectorAll('.tfv2-node').forEach(item => {
      item.classList.remove('active');
      item.classList.remove('is-selected');
    });

    element.classList.add('active');
    element.classList.add('is-selected');

    renderEditor(node);
  }

  document.addEventListener('click', event => {
    const nodeElement = event.target.closest('.tfv2-node');

    if (!nodeElement || event.target.closest('[data-node-toggle]')) {
      return;
    }

    selectNode(nodeElement);
  });

  function boot() {
    const active = document.querySelector('.tfv2-node.active') || document.querySelector('.tfv2-node');

    if (active) {
      selectNode(active);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();