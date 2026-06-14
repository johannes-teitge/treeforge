(function () {
  const state = window.TreeForgeExplorerV3 || { page: 'home', workspace: 'draft' };
  const empty = document.getElementById('tfv3Empty');
  const editor = document.getElementById('tfv3Editor');
  const hint = document.getElementById('tfv3EditorHint');

  let currentNode = null;

  function esc(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function toast(message, type) {
    let box = document.getElementById('tfv3Toast');
    if (!box) {
      box = document.createElement('div');
      box.id = 'tfv3Toast';
      document.body.appendChild(box);
    }
    box.textContent = message;
    box.className = type === 'error' ? 'error show' : 'show';
    window.setTimeout(() => box.classList.remove('show', 'error'), 4200);
  }

  function rawNodeFromButton(button) {
    try {
      const inspect = JSON.parse(button.getAttribute('data-node-json') || '{}');
      return inspect.raw || inspect;
    } catch (error) {
      return {};
    }
  }

  function normalizeProperties(node) {
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

  function value(node, group, key, legacy, fallback) {
    const p = normalizeProperties(node);
    if (p[group] && typeof p[group][key] !== 'undefined') return p[group][key];

    for (const k of legacy || []) {
      if (node.properties && typeof node.properties[k] !== 'undefined') return node.properties[k];
      if (typeof node[k] !== 'undefined') return node[k];
    }
    return fallback ?? '';
  }

  function input(path, label, val, placeholder, type) {
    return `<label>${esc(label)}<input type="${esc(type || 'text')}" data-prop="${esc(path)}" value="${esc(val)}" placeholder="${esc(placeholder || '')}"></label>`;
  }

  function area(path, label, val, placeholder, rows, cls) {
    return `<label>${esc(label)}<textarea class="${esc(cls || '')}" rows="${Number(rows || 4)}" data-prop="${esc(path)}" placeholder="${esc(placeholder || '')}">${esc(val)}</textarea></label>`;
  }

  function select(path, label, val, options) {
    return `<label>${esc(label)}<select data-prop="${esc(path)}">${options.map(o => {
      const v = typeof o === 'string' ? o : o.value;
      const t = typeof o === 'string' ? o : o.label;
      return `<option value="${esc(v)}"${String(val) === String(v) ? ' selected' : ''}>${esc(t)}</option>`;
    }).join('')}</select></label>`;
  }

  function baseInput(name, label, val) {
    return `<label>${esc(label)}<input data-base="${esc(name)}" value="${esc(val)}"></label>`;
  }

  function baseSelect(name, label, val, options) {
    return `<label>${esc(label)}<select data-base="${esc(name)}">${options.map(o => `<option value="${esc(o.value)}"${String(val) === String(o.value) ? ' selected' : ''}>${esc(o.label)}</option>`).join('')}</select></label>`;
  }

  function group(title, body, open) {
    return `<details class="tfv3-group"${open ? ' open' : ''}><summary>${esc(title)}</summary><div>${body}</div></details>`;
  }

  function contentFields(node) {
    const type = String(node.type || '').toLowerCase();

    if (type.includes('image')) {
      return [
        input('content.media_id', 'Media ID / Pfad', value(node, 'content', 'media_id', ['media_id','mediaId','image','src']), 'uploads/bild.jpg'),
        input('content.alt', 'Alt-Text', value(node, 'content', 'alt', ['alt','alt_text','altText']), 'Bildbeschreibung'),
        area('content.caption', 'Caption', value(node, 'content', 'caption', ['caption']), 'Bildunterschrift', 3)
      ].join('');
    }

    if (type.includes('button')) {
      return input('content.label', 'Button Text', value(node, 'content', 'label', ['label','text','title']), 'Mehr erfahren');
    }

    if (type.includes('markdown')) {
      return area('content.markdown', 'Markdown', value(node, 'content', 'markdown', ['markdown','content','text']), 'Markdown Inhalt', 10, 'code');
    }

    if (type.includes('html')) {
      return area('content.html', 'HTML', value(node, 'content', 'html', ['html','content']), 'HTML Inhalt', 10, 'code');
    }

    if (type.includes('css')) {
      return area('content.css', 'CSS', value(node, 'content', 'css', ['css','content']), 'CSS Inhalt', 10, 'code');
    }

    return area('content.text', 'Text / Content', value(node, 'content', 'text', ['text','content','value']), 'Textinhalt', 8);
  }

  function layoutFields(node) {
    return `<div class="tfv3-grid">
      ${select('layout.display', 'Display', value(node, 'layout', 'display', ['display'], 'block'), ['block','flex','grid','inline-block','none'])}
      ${select('layout.alignment', 'Ausrichtung', value(node, 'layout', 'alignment', ['alignment','align'], ''), [
        {value:'', label:'Standard'}, {value:'left', label:'Links'}, {value:'center', label:'Zentriert'}, {value:'right', label:'Rechts'}, {value:'stretch', label:'Stretch'}
      ])}
      ${input('layout.width', 'Width', value(node, 'layout', 'width', ['width']), '100%')}
      ${input('layout.max_width', 'Max Width', value(node, 'layout', 'max_width', ['max_width','maxWidth']), '1200px')}
      ${input('layout.min_height', 'Min Height', value(node, 'layout', 'min_height', ['min_height','minHeight']), '320px')}
      ${input('layout.columns', 'Columns', value(node, 'layout', 'columns', ['columns','count']), '2')}
    </div>`;
  }

  function spacingFields(node) {
    return `<div class="tfv3-grid">
      ${input('spacing.margin', 'Margin', value(node, 'spacing', 'margin', ['margin']), '1rem 0')}
      ${input('spacing.padding', 'Padding', value(node, 'spacing', 'padding', ['padding']), '2rem')}
      ${input('spacing.gap', 'Gap', value(node, 'spacing', 'gap', ['gap']), '1rem')}
    </div>`;
  }

  function designFields(node) {
    return `<div class="tfv3-grid">
      ${input('design.background', 'Background', value(node, 'design', 'background', ['background']), 'var(--tf-primary-10)')}
      ${input('design.color', 'Text Color', value(node, 'design', 'color', ['color']), 'var(--tf-text)')}
      ${input('design.border', 'Border', value(node, 'design', 'border', ['border']), '1px solid #ddd')}
      ${input('design.border_radius', 'Radius', value(node, 'design', 'border_radius', ['border_radius','radius']), '.75rem')}
      ${input('design.box_shadow', 'Shadow', value(node, 'design', 'box_shadow', ['box_shadow','shadow']), '0 8px 24px rgba(...)')}
      ${input('design.style', 'Style', value(node, 'design', 'style', ['style']), 'primary')}
    </div>`;
  }

  function behaviorFields(node) {
    const p = normalizeProperties(node);
    const schedule = p.behavior.schedule || node.schedule || {};
    const isSchedule = String(node.type || '').toLowerCase().includes('schedule');

    let html = `<div class="tfv3-grid">
      ${input('behavior.url', 'URL / Link', value(node, 'behavior', 'url', ['url','href','link','link_url']), '/kontakt')}
      ${select('behavior.target', 'Target', value(node, 'behavior', 'target', ['target','link_target'], '_self'), [
        {value:'_self', label:'Gleiches Fenster'}, {value:'_blank', label:'Neues Fenster'}
      ])}
      ${select('behavior.zoom', 'Zoom', value(node, 'behavior', 'zoom', ['zoom'], ''), [
        {value:'', label:'Nein'}, {value:'lightbox', label:'Lightbox'}, {value:'bump', label:'Bump'}
      ])}
    </div>`;

    if (isSchedule) {
      html += `<div class="tfv3-grid">
        ${input('behavior.schedule.active_from', 'Aktiv von', schedule.active_from || '', '', 'date')}
        ${input('behavior.schedule.active_until', 'Aktiv bis', schedule.active_until || '', '', 'date')}
        ${input('behavior.schedule.time_from', 'Uhrzeit von', schedule.time_from || '', '', 'time')}
        ${input('behavior.schedule.time_until', 'Uhrzeit bis', schedule.time_until || '', '', 'time')}
        ${input('behavior.schedule.timezone', 'Zeitzone', schedule.timezone || 'Europe/Berlin', 'Europe/Berlin')}
      </div>
      ${input('behavior.schedule.days', 'Tage', Array.isArray(schedule.days) ? schedule.days.join(',') : '', 'mo,tu,we,th,fr')}`;
    }

    return html;
  }

  function advancedFields(node) {
    return `<div class="tfv3-grid">
      ${input('advanced.css_class', 'CSS Class', value(node, 'advanced', 'css_class', ['css_class']), 'hero-section')}
      ${input('advanced.css_id', 'CSS ID', value(node, 'advanced', 'css_id', ['css_id']), 'hero')}
    </div>
    ${area('advanced.custom_style', 'Custom Style', value(node, 'advanced', 'custom_style', ['custom_style']), 'Optional', 4, 'code')}`;
  }

  function customCssFields(node) {
    const p = normalizeProperties(node);
    return `${area('custom_css', 'Custom CSS Properties', p.custom_css, 'display: flex\\ngap: 1rem\\nalign-items: center', 8, 'code')}
    <small>Format: <code>bezeichner: wert</code> pro Zeile. Parser/Validierung folgt später.</small>`;
  }

  function render(node) {
    currentNode = node;
    empty.hidden = true;
    editor.hidden = false;

    const children = Array.isArray(node.children) ? node.children.length : 0;
    hint.textContent = `${node.type || 'Node'} · ${node.id || ''} · ${children} Kinder`;

    editor.innerHTML = `
      <div class="tfv3-savebar top">
        <button type="button" class="tfv3-btn primary" data-save disabled>Eigenschaften übernehmen</button>
      </div>

      <section class="tfv3-info">
        <strong>${esc(node.type || 'Node')}</strong>
        <span>${esc(node.id || '')}</span>
      </section>

      <section class="tfv3-base">
        ${baseInput('title', 'Titel', node.title || '')}
        <div class="tfv3-grid">
          ${baseSelect('status', 'Status', node.status || 'active', [{value:'active', label:'Aktiv'}, {value:'inactive', label:'Inaktiv'}])}
          ${baseSelect('visibility', 'Sichtbarkeit', node.visibility || 'visible', [{value:'visible', label:'Sichtbar'}, {value:'hidden', label:'Versteckt'}])}
        </div>
        <label>Editor-Notiz<textarea data-base="editor_note" rows="3">${esc(node.editor_note || '')}</textarea></label>
      </section>

      ${group('Content', contentFields(node), true)}
      ${group('Layout', layoutFields(node), false)}
      ${group('Spacing', spacingFields(node), false)}
      ${group('Design', designFields(node), false)}
      ${group('Behavior', behaviorFields(node), false)}
      ${group('Advanced', advancedFields(node), false)}
      ${group('Custom CSS', customCssFields(node), false)}

      <div class="tfv3-savebar bottom">
        <button type="button" class="tfv3-btn secondary" data-cancel>Abbrechen</button>
        <button type="button" class="tfv3-btn primary" data-save disabled>Eigenschaften übernehmen</button>
      </div>
    `;
  }

  function setDeep(target, path, val) {
    const parts = String(path).split('.').filter(Boolean);
    if (parts.length === 0) return;

    let cur = target;
    for (let i = 0; i < parts.length - 1; i++) {
      if (!cur[parts[i]] || typeof cur[parts[i]] !== 'object' || Array.isArray(cur[parts[i]])) {
        cur[parts[i]] = {};
      }
      cur = cur[parts[i]];
    }

    const key = parts[parts.length - 1];
    if (key === 'days' && typeof val === 'string') {
      cur[key] = val.split(',').map(x => x.trim()).filter(Boolean);
    } else {
      cur[key] = val;
    }
  }

  function collect() {
    const base = {};
    const properties = {};

    editor.querySelectorAll('[data-base]').forEach(field => {
      base[field.dataset.base] = field.value;
    });

    editor.querySelectorAll('[data-prop]').forEach(field => {
      const path = field.dataset.prop;
      if (path === 'custom_css') {
        properties.custom_css = field.value;
      } else {
        setDeep(properties, path, field.value);
      }
    });

    return { base, properties };
  }

  function dirty(on) {
    editor.classList.toggle('dirty', on);
    editor.querySelectorAll('[data-save]').forEach(btn => {
      btn.disabled = !on;
      btn.textContent = 'Eigenschaften übernehmen';
    });
  }

  async function save() {
    if (!currentNode || !currentNode.id) {
      toast('Keine Node ausgewählt.', 'error');
      return;
    }

    const payload = collect();

    editor.querySelectorAll('[data-save]').forEach(btn => {
      btn.disabled = true;
      btn.textContent = 'Speichert...';
    });

    try {
      const response = await fetch('/api/explorer-v2/mutate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify({
          page: state.page || 'home',
          workspace: state.workspace || 'draft',
          action: 'update-node',
          payload: {
            node_id: currentNode.id,
            base: payload.base,
            properties: payload.properties
          }
        })
      });

      const json = await response.json().catch(() => null);
      if (!response.ok || !json || json.ok !== true) {
        throw new Error((json && json.error) ? json.error : 'Speichern fehlgeschlagen.');
      }

      toast('Eigenschaften gespeichert.');
      sessionStorage.setItem('tfv2.lastAddedNode', currentNode.id);

      const url = new URL(window.location.href);
      url.searchParams.set('workspace', 'draft');
      url.searchParams.set('_', String(Date.now()));
      setTimeout(() => window.location.href = url.toString(), 900);
    } catch (error) {
      toast(error.message || 'Speichern fehlgeschlagen.', 'error');
      dirty(true);
    }
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('.tf-tree-node-button');
    if (!button) return;

    document.querySelectorAll('.tf-tree-node-button').forEach(b => b.classList.remove('active'));
    button.classList.add('active');

    render(rawNodeFromButton(button));
  });

  document.addEventListener('input', event => {
    if (event.target.closest('#tfv3Editor')) dirty(true);
  }, true);

  document.addEventListener('change', event => {
    if (event.target.closest('#tfv3Editor')) dirty(true);
  }, true);

  editor.addEventListener('click', event => {
    if (event.target.closest('[data-save]')) {
      event.preventDefault();
      save();
    }

    if (event.target.closest('[data-cancel]')) {
      event.preventDefault();
      if (currentNode) render(currentNode);
    }
  });

  function initTreeToggles() {
    document.querySelectorAll('.tf-tree-toggle').forEach(toggle => {
      toggle.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();

        const li = toggle.closest('.tf-tree-page, .tf-tree-node');
        if (!li) return;

        const open = li.classList.toggle('is-open');
        li.classList.toggle('is-closed', !open);
        toggle.textContent = open ? '▾' : '▸';
      });
    });

    const expand = document.getElementById('tfExpandAll');
    const collapse = document.getElementById('tfCollapseAll');

    if (expand) {
      expand.addEventListener('click', () => {
        document.querySelectorAll('.tf-tree-page, .tf-tree-node.has-children').forEach(li => {
          li.classList.add('is-open');
          li.classList.remove('is-closed');
          const t = li.querySelector(':scope > .tf-tree-row .tf-tree-toggle, :scope > .tf-tree-toggle');
          if (t) t.textContent = '▾';
        });
      });
    }

    if (collapse) {
      collapse.addEventListener('click', () => {
        document.querySelectorAll('.tf-tree-node.has-children').forEach(li => {
          li.classList.remove('is-open');
          li.classList.add('is-closed');
          const t = li.querySelector(':scope > .tf-tree-row .tf-tree-toggle, :scope > .tf-tree-toggle');
          if (t) t.textContent = '▸';
        });
      });
    }
  }

  initTreeToggles();
})();