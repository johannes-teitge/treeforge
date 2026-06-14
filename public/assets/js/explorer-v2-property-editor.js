(function () {
  function esc(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function propGroups(node) {
    const p = node.properties || {};

    return {
      content: p.content || {},
      layout: p.layout || {},
      spacing: p.spacing || {},
      design: p.design || {},
      behavior: p.behavior || {},
      advanced: p.advanced || {},
      custom_css: typeof p.custom_css === 'string' ? p.custom_css : ''
    };
  }

  function propValue(node, group, key, legacyKeys, fallback) {
    const groups = propGroups(node);

    if (groups[group] && typeof groups[group][key] !== 'undefined') {
      return groups[group][key];
    }

    for (const legacyKey of legacyKeys || []) {
      if (node.properties && typeof node.properties[legacyKey] !== 'undefined') {
        return node.properties[legacyKey];
      }

      if (typeof node[legacyKey] !== 'undefined') {
        return node[legacyKey];
      }
    }

    return fallback ?? '';
  }

  function input(path, label, value, placeholder, type) {
    return `
      <label>
        ${esc(label)}
        <input type="${esc(type || 'text')}"
               data-node-property="${esc(path)}"
               value="${esc(value)}"
               placeholder="${esc(placeholder || '')}">
      </label>
    `;
  }

  function textarea(path, label, value, placeholder, rows, cssClass) {
    return `
      <label>
        ${esc(label)}
        <textarea class="${esc(cssClass || '')}"
                  rows="${Number(rows || 4)}"
                  data-node-property="${esc(path)}"
                  placeholder="${esc(placeholder || '')}">${esc(value)}</textarea>
      </label>
    `;
  }

  function select(path, label, value, options) {
    return `
      <label>
        ${esc(label)}
        <select data-node-property="${esc(path)}">
          ${(options || []).map(option => {
            const val = typeof option === 'string' ? option : option.value;
            const text = typeof option === 'string' ? option : option.label;

            return `<option value="${esc(val)}"${String(value) === String(val) ? ' selected' : ''}>${esc(text)}</option>`;
          }).join('')}
        </select>
      </label>
    `;
  }

  function baseInput(path, label, value) {
    return `
      <label>
        ${esc(label)}
        <input data-node-base="${esc(path)}" value="${esc(value)}">
      </label>
    `;
  }

  function baseTextarea(path, label, value) {
    return `
      <label>
        ${esc(label)}
        <textarea data-node-base="${esc(path)}" rows="3">${esc(value)}</textarea>
      </label>
    `;
  }

  function group(title, html, open) {
    return `
      <details class="tfv2-prop-group"${open ? ' open' : ''}>
        <summary>${esc(title)}</summary>
        <div class="tfv2-prop-group-body">
          ${html}
        </div>
      </details>
    `;
  }

  function nodeType(node) {
    return String(node.type || '').toLowerCase();
  }

  function contentFields(node) {
    const type = nodeType(node);

    if (type.includes('image')) {
      return `
        ${input('content.media_id', 'Media ID / Pfad', propValue(node, 'content', 'media_id', ['media_id', 'mediaId', 'image', 'src'], ''), 'uploads/bild.jpg')}
        ${input('content.alt', 'Alt-Text', propValue(node, 'content', 'alt', ['alt', 'alt_text', 'altText'], ''), 'Bildbeschreibung')}
        ${textarea('content.caption', 'Caption', propValue(node, 'content', 'caption', ['caption'], ''), 'Bildunterschrift', 3)}
      `;
    }

    if (type.includes('button')) {
      return `
        ${input('content.label', 'Button Text', propValue(node, 'content', 'label', ['label', 'text', 'title'], ''), 'Mehr erfahren')}
      `;
    }

    if (type.includes('codeblock') || type.includes('codesnippet') || type.includes('snippet')) {
      return `
        ${select('content.language', 'Sprache', propValue(node, 'content', 'language', ['language', 'lang'], 'php'), [
          { value: 'plaintext', label: 'Plain Text' },
          { value: 'php', label: 'PHP' },
          { value: 'html', label: 'HTML' },
          { value: 'css', label: 'CSS' },
          { value: 'javascript', label: 'JavaScript' },
          { value: 'json', label: 'JSON' },
          { value: 'sql', label: 'SQL' },
          { value: 'bash', label: 'Bash / Shell' },
          { value: 'ini', label: 'INI / Config' },
          { value: 'yaml', label: 'YAML' },
          { value: 'xml', label: 'XML' },
          { value: 'delphi', label: 'Delphi / Pascal' }
        ])}
        ${textarea('content.code', 'Code', propValue(node, 'content', 'code', ['code', 'content', 'text'], ''), 'Code hier einfügen', 14, 'code')}
        ${input('content.caption', 'Caption', propValue(node, 'content', 'caption', ['caption'], ''), 'optional')}
        <div class="tfv2-field-grid">
          ${select('content.show_line_numbers', 'Zeilennummern', propValue(node, 'content', 'show_line_numbers', ['show_line_numbers', 'line_numbers'], '1'), [
            { value: '1', label: 'Ja' },
            { value: '0', label: 'Nein' }
          ])}
          ${select('content.wrap', 'Zeilenumbruch', propValue(node, 'content', 'wrap', ['wrap'], '0'), [
            { value: '0', label: 'Nein, horizontal scrollen' },
            { value: '1', label: 'Ja, umbrechen' }
          ])}
        </div>
      `;
    }

    if (type.includes('heading') || type.includes('headline') || type.includes('title')) {
      return `
        ${input('content.text', 'Überschrift', propValue(node, 'content', 'text', ['text', 'content', 'title'], ''), 'Überschrift eingeben')}
        ${select('content.level', 'Ebene', propValue(node, 'content', 'level', ['level'], 'h2'), [
          { value: 'h1', label: 'H1 – Hauptüberschrift' },
          { value: 'h2', label: 'H2 – Abschnitt' },
          { value: 'h3', label: 'H3 – Unterabschnitt' },
          { value: 'h4', label: 'H4' },
          { value: 'h5', label: 'H5' },
          { value: 'h6', label: 'H6' }
        ])}
      `;
    }

    if (type.includes('markdown')) {
      return textarea('content.markdown', 'Markdown', propValue(node, 'content', 'markdown', ['markdown', 'content', 'text'], ''), 'Markdown Inhalt', 10, 'code');
    }

    if (type.includes('html')) {
      return textarea('content.html', 'HTML', propValue(node, 'content', 'html', ['html', 'content'], ''), 'HTML Inhalt', 10, 'code');
    }

    if (type.includes('css')) {
      return textarea('content.css', 'CSS', propValue(node, 'content', 'css', ['css', 'content'], ''), 'CSS Inhalt', 10, 'code');
    }

    return textarea('content.text', 'Text / Content', propValue(node, 'content', 'text', ['text', 'content', 'value'], ''), 'Textinhalt', 8);
  }

  function layoutFields(node) {
    return `
      <div class="tfv2-field-grid">
        ${select('layout.display', 'Display', propValue(node, 'layout', 'display', ['display'], 'block'), ['block', 'flex', 'grid', 'inline-block', 'none'])}
        ${select('layout.alignment', 'Ausrichtung', propValue(node, 'layout', 'alignment', ['alignment', 'align'], ''), [
          { value: '', label: 'Standard' },
          { value: 'left', label: 'Links' },
          { value: 'center', label: 'Zentriert' },
          { value: 'right', label: 'Rechts' },
          { value: 'stretch', label: 'Stretch' }
        ])}
        ${input('layout.width', 'Width', propValue(node, 'layout', 'width', ['width'], ''), '100%')}
        ${input('layout.max_width', 'Max Width', propValue(node, 'layout', 'max_width', ['max_width', 'maxWidth'], ''), '1200px')}
        ${input('layout.min_height', 'Min Height', propValue(node, 'layout', 'min_height', ['min_height', 'minHeight'], ''), '320px')}
        ${input('layout.columns', 'Columns', propValue(node, 'layout', 'columns', ['columns', 'count'], ''), '2')}
      </div>
    `;
  }

  function spacingFields(node) {
    return `
      <div class="tfv2-field-grid">
        ${input('spacing.margin', 'Margin', propValue(node, 'spacing', 'margin', ['margin'], ''), '1rem 0')}
        ${input('spacing.padding', 'Padding', propValue(node, 'spacing', 'padding', ['padding'], ''), '2rem')}
        ${input('spacing.gap', 'Gap', propValue(node, 'spacing', 'gap', ['gap'], ''), '1rem')}
      </div>
    `;
  }

  function designFields(node) {
    return `
      <div class="tfv2-field-grid">
        ${input('design.background', 'Background', propValue(node, 'design', 'background', ['background'], ''), 'var(--tf-primary-10)')}
        ${input('design.color', 'Text Color', propValue(node, 'design', 'color', ['color'], ''), 'var(--tf-text)')}
        ${input('design.border', 'Border', propValue(node, 'design', 'border', ['border'], ''), '1px solid #ddd')}
        ${input('design.border_radius', 'Radius', propValue(node, 'design', 'border_radius', ['border_radius', 'radius'], ''), '.75rem')}
        ${input('design.box_shadow', 'Shadow', propValue(node, 'design', 'box_shadow', ['box_shadow', 'shadow'], ''), '0 8px 24px rgba(...)')}
        ${input('design.style', 'Style', propValue(node, 'design', 'style', ['style'], ''), 'primary / secondary')}
      </div>
    `;
  }

  function behaviorFields(node) {
    const type = nodeType(node);
    const groups = propGroups(node);
    const schedule =
      groups.behavior.schedule ||
      (node.properties && node.properties.schedule) ||
      node.schedule ||
      {};

    let html = `
      <div class="tfv2-field-grid">
        ${input('behavior.url', 'URL / Link', propValue(node, 'behavior', 'url', ['url', 'href', 'link', 'link_url'], ''), '/kontakt')}
        ${select('behavior.target', 'Target', propValue(node, 'behavior', 'target', ['target', 'link_target'], '_self'), [
          { value: '_self', label: 'Gleiches Fenster' },
          { value: '_blank', label: 'Neues Fenster' }
        ])}
        ${select('behavior.zoom', 'Zoom', propValue(node, 'behavior', 'zoom', ['zoom'], ''), [
          { value: '', label: 'Nein' },
          { value: 'lightbox', label: 'Lightbox' },
          { value: 'bump', label: 'Bump' }
        ])}
      </div>
    `;

    if (type.includes('schedule')) {
      html += `
        <div class="tfv2-field-grid">
          ${input('behavior.schedule.active_from', 'Aktiv von', schedule.active_from || '', '', 'date')}
          ${input('behavior.schedule.active_until', 'Aktiv bis', schedule.active_until || '', '', 'date')}
          ${input('behavior.schedule.time_from', 'Uhrzeit von', schedule.time_from || '', '', 'time')}
          ${input('behavior.schedule.time_until', 'Uhrzeit bis', schedule.time_until || '', '', 'time')}
          ${input('behavior.schedule.timezone', 'Zeitzone', schedule.timezone || 'Europe/Berlin', 'Europe/Berlin')}
        </div>
        ${input('behavior.schedule.days', 'Tage', Array.isArray(schedule.days) ? schedule.days.join(',') : '', 'mo,tu,we,th,fr')}
      `;
    }

    return html;
  }

  function advancedFields(node) {
    return `
      <div class="tfv2-field-grid">
        ${input('advanced.css_class', 'CSS Class', propValue(node, 'advanced', 'css_class', ['css_class'], ''), 'hero-section')}
        ${input('advanced.css_id', 'CSS ID', propValue(node, 'advanced', 'css_id', ['css_id'], ''), 'hero')}
      </div>
      ${textarea('advanced.custom_style', 'Custom Style', propValue(node, 'advanced', 'custom_style', ['custom_style'], ''), 'Optional', 4, 'code')}
    `;
  }

  function customCssFields(node) {
    const groups = propGroups(node);

    return `
      ${textarea('custom_css', 'Custom CSS Properties', groups.custom_css, 'display: flex\\ngap: 1rem\\nalign-items: center', 8, 'code')}
      <small class="tfv2-help">Format: <code>bezeichner: wert</code> pro Zeile. Parser/Validierung folgt später.</small>
    `;
  }

  function baseFields(node) {
    return `
      <div class="tfv2-prop-base">
        ${baseInput('title', 'Titel', node.title || '')}

        <div class="tfv2-field-grid">
          <label>
            Status
            <select data-node-base="status">
              <option value="active"${(node.status || 'active') === 'active' ? ' selected' : ''}>Aktiv</option>
              <option value="inactive"${node.status === 'inactive' ? ' selected' : ''}>Inaktiv</option>
            </select>
          </label>

          <label>
            Sichtbarkeit
            <select data-node-base="visibility">
              <option value="visible"${(node.visibility || 'visible') === 'visible' ? ' selected' : ''}>Sichtbar</option>
              <option value="hidden"${node.visibility === 'hidden' ? ' selected' : ''}>Versteckt</option>
            </select>
          </label>
        </div>

        ${baseTextarea('editor_note', 'Editor-Notiz', node.editor_note || '')}
      </div>
    `;
  }

  function render(node) {
    const panel =
      document.querySelector('.tfv2-tab-panel[data-panel="editor"]') ||
      document.querySelector('.tfv2-editor-panel .tfv2-editor') ||
      document.querySelector('.tfv2-editor-panel');

    if (!panel) {
      return;
    }

    panel.innerHTML = `
      <div class="tfv2-property-editor" data-current-node-id="${esc(node.id || '')}">
        <div class="tfv2-property-top-actions">
          <button type="button" class="tfv2-btn tfv2-property-save" data-node-save="1" disabled>Eigenschaften übernehmen</button>
        </div>

        ${baseFields(node)}
        ${group('Content', contentFields(node), true)}
        ${group('Layout', layoutFields(node), false)}
        ${group('Spacing', spacingFields(node), false)}
        ${group('Design', designFields(node), false)}
        ${group('Behavior', behaviorFields(node), false)}
        ${group('Advanced', advancedFields(node), false)}
        ${group('Custom CSS', customCssFields(node), false)}

        <div class="tfv2-property-bottom-actions">
          <button type="button" class="tfv2-btn secondary" data-property-cancel="1">Abbrechen</button>
          <button type="button" class="tfv2-btn tfv2-property-save" data-node-save="1" disabled>Eigenschaften übernehmen</button>
        </div>
      </div>
    `;
  }

  function parseNode(element) {
    if (element && element.dataset.nodeJson) {
      try {
        return JSON.parse(element.dataset.nodeJson || '{}') || {};
      } catch (error) {
        // fallback below
      }
    }

    const strong = element ? element.querySelector('strong') : null;
    const small = element ? element.querySelector('small') : null;
    const meta = small ? small.textContent.trim() : '';
    const parts = meta.split('·').map(part => part.trim());

    return {
      id: element ? (element.dataset.nodeId || parts[0] || '') : '',
      type: parts[1] || 'Node',
      title: strong ? strong.textContent.trim() : 'Node',
      status: 'active',
      visibility: 'visible',
      properties: {}
    };
  }

  document.addEventListener('click', function (event) {
    const nodeElement = event.target.closest('.tfv2-node');

    if (!nodeElement || event.target.closest('[data-node-toggle], .tfv2-node-menu')) {
      return;
    }

    window.setTimeout(() => render(parseNode(nodeElement)), 0);
  }, true);

  function boot() {
    const active = document.querySelector('.tfv2-node.active') || document.querySelector('.tfv2-node');

    if (active) {
      render(parseNode(active));
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    window.setTimeout(boot, 120);
  }

  window.TreeForgeV2PropertyEditor = {
    render,
    parseNode
  };
})();