(function () {
  const registry = [
    {
      type: 'TextNode',
      label: 'Text',
      icon: '📝',
      group: 'Content',
      description: 'Einfacher Textblock.',
      defaults: {
        type: 'TextNode',
        title: 'Neuer Text',
        text: ''
      }
    },
    {
      type: 'MarkdownNode',
      label: 'Markdown',
      icon: '⬇️',
      group: 'Content',
      description: 'Formatierter Inhalt mit Markdown.',
      defaults: {
        type: 'MarkdownNode',
        title: 'Neuer Markdown Block',
        markdown: ''
      }
    },
    {
      type: 'HtmlNode',
      label: 'HTML',
      icon: '📄',
      group: 'Code',
      description: 'Freier HTML-Block.',
      defaults: {
        type: 'HtmlNode',
        title: 'Neuer HTML Block',
        html: ''
      }
    },
    {
      type: 'ImageNode',
      label: 'Bild',
      icon: '🖼️',
      group: 'Media',
      description: 'Bild aus der Medienbibliothek.',
      defaults: {
        type: 'ImageNode',
        title: 'Neues Bild',
        media_id: '',
        alt: '',
        caption: '',
        display: 'content',
        link_url: '',
        link_target: '_self'
      }
    },
    {
      type: 'ButtonNode',
      label: 'Button',
      icon: '🔘',
      group: 'Interaction',
      description: 'Button mit Linkziel.',
      defaults: {
        type: 'ButtonNode',
        title: 'Neuer Button',
        label: 'Mehr erfahren',
        url: '',
        target: '_self'
      }
    },
    {
      type: 'ColumnsNode',
      label: 'Columns',
      icon: '▦',
      group: 'Layout',
      description: 'Mehrspaltiges Layout.',
      defaults: {
        type: 'ColumnsNode',
        title: 'Neue Spalten',
        columns: 2,
        gap: '1rem',
        children: [
          {
            id: '',
            type: 'ColumnNode',
            title: 'Spalte 1',
            children: []
          },
          {
            id: '',
            type: 'ColumnNode',
            title: 'Spalte 2',
            children: []
          }
        ]
      }
    },
    {
      type: 'CssNode',
      label: 'CSS',
      icon: '🎨',
      group: 'Code',
      description: 'Seitenspezifischer CSS-Code.',
      defaults: {
        type: 'CssNode',
        title: 'Neuer CSS Block',
        css: ''
      }
    }
  ];

  window.TreeForgeV2NodeTypes = window.TreeForgeV2NodeTypes || [];

  registry.forEach(item => {
    const exists = window.TreeForgeV2NodeTypes.some(existing => existing.type === item.type);

    if (!exists) {
      window.TreeForgeV2NodeTypes.push(item);
    }
  });

  window.TreeForgeV2NodeTypeRegistry = {
    all() {
      return window.TreeForgeV2NodeTypes.slice();
    },

    byType(type) {
      return window.TreeForgeV2NodeTypes.find(item => item.type === type) || null;
    },

    register(item) {
      if (!item || !item.type) {
        return;
      }

      const index = window.TreeForgeV2NodeTypes.findIndex(existing => existing.type === item.type);

      if (index >= 0) {
        window.TreeForgeV2NodeTypes[index] = item;
      } else {
        window.TreeForgeV2NodeTypes.push(item);
      }
    }
  };
})();
/* PATCH 091 ROBUST CONTAINER TYPES */
(function () {
  function register(item) {
    if (!item || !item.type) {
      return;
    }

    if (window.TreeForgeV2NodeTypeRegistry && typeof window.TreeForgeV2NodeTypeRegistry.register === 'function') {
      window.TreeForgeV2NodeTypeRegistry.register(item);
      return;
    }

    window.TreeForgeV2NodeTypes = window.TreeForgeV2NodeTypes || [];

    const index = window.TreeForgeV2NodeTypes.findIndex(existing => existing.type === item.type);

    if (index >= 0) {
      window.TreeForgeV2NodeTypes[index] = item;
    } else {
      window.TreeForgeV2NodeTypes.push(item);
    }
  }

  const containerDefaults = {
    display: 'block',
    width: '',
    max_width: '',
    min_height: '',
    margin: '',
    padding: '',
    gap: '',
    background: '',
    border: '',
    border_radius: '',
    box_shadow: '',
    css_class: '',
    css_id: '',
    custom_style: ''
  };

  register({
    type: 'ContainerNode',
    label: 'Container',
    icon: '📦',
    group: 'Container',
    description: 'Layout-Container für Abstände, Hintergrund, Rahmen und Kinder.',
    defaults: {
      type: 'ContainerNode',
      title: 'Neuer Container',
      status: 'active',
      visibility: 'visible',
      container: Object.assign({}, containerDefaults),
      children: []
    }
  });

  register({
    type: 'ScheduleContainerNode',
    label: 'Zeitsteuerung',
    icon: '⏱️',
    group: 'Container',
    description: 'Container, der Inhalte zeitgesteuert anzeigt.',
    defaults: {
      type: 'ScheduleContainerNode',
      title: 'Zeitgesteuerter Container',
      status: 'active',
      visibility: 'visible',
      container: Object.assign({}, containerDefaults),
      schedule: {
        active_from: '',
        active_until: '',
        days: [],
        time_from: '',
        time_until: '',
        timezone: 'Europe/Berlin'
      },
      children: []
    }
  });
})();

/* PATCH 124 HEADING NODE TYPE */
(function () {
  function register(item) {
    if (!item || !item.type) {
      return;
    }

    if (window.TreeForgeV2NodeTypeRegistry && typeof window.TreeForgeV2NodeTypeRegistry.register === 'function') {
      window.TreeForgeV2NodeTypeRegistry.register(item);
      return;
    }

    window.TreeForgeV2NodeTypes = window.TreeForgeV2NodeTypes || [];
    const index = window.TreeForgeV2NodeTypes.findIndex(existing => existing.type === item.type);

    if (index >= 0) {
      window.TreeForgeV2NodeTypes[index] = item;
    } else {
      window.TreeForgeV2NodeTypes.push(item);
    }
  }

  register({
    type: 'HeadingNode',
    label: 'Überschrift',
    icon: '🔠',
    group: 'Content',
    description: 'H1-H6 Überschrift ohne HTML schreiben zu müssen.',
    defaults: {
      type: 'HeadingNode',
      title: 'Neue Überschrift',
      status: 'active',
      visibility: 'visible',
      editor_note: '',
      properties: {
        content: {
          text: 'Neue Überschrift',
          level: 'h2'
        },
        layout: {
          display: 'block',
          alignment: '',
          width: '',
          max_width: '',
          min_height: '',
          columns: ''
        },
        spacing: {
          margin: '',
          padding: '',
          gap: ''
        },
        design: {
          background: '',
          color: '',
          border: '',
          border_radius: '',
          box_shadow: '',
          style: ''
        },
        behavior: {
          url: '',
          target: '_self',
          zoom: ''
        },
        advanced: {
          css_class: '',
          css_id: '',
          custom_style: ''
        },
        custom_css: ''
      },
      children: []
    }
  });
})();

/* PATCH 125 CODEBLOCK NODE TYPE */
(function () {
  function register(item) {
    if (!item || !item.type) {
      return;
    }

    if (window.TreeForgeV2NodeTypeRegistry && typeof window.TreeForgeV2NodeTypeRegistry.register === 'function') {
      window.TreeForgeV2NodeTypeRegistry.register(item);
      return;
    }

    window.TreeForgeV2NodeTypes = window.TreeForgeV2NodeTypes || [];
    const index = window.TreeForgeV2NodeTypes.findIndex(existing => existing.type === item.type);

    if (index >= 0) {
      window.TreeForgeV2NodeTypes[index] = item;
    } else {
      window.TreeForgeV2NodeTypes.push(item);
    }
  }

  register({
    type: 'CodeBlockNode',
    label: 'Code / Highlighter',
    icon: '💻',
    group: 'Content',
    description: 'Code sichtbar darstellen, aber nicht ausführen.',
    defaults: {
      type: 'CodeBlockNode',
      title: 'Neuer Code-Block',
      status: 'active',
      visibility: 'visible',
      editor_note: '',
      properties: {
        content: {
          code: "<?php\necho 'Hallo TreeForge';\n",
          language: 'php',
          caption: '',
          show_line_numbers: '1',
          wrap: '0'
        },
        layout: {
          display: 'block',
          alignment: '',
          width: '',
          max_width: '',
          min_height: '',
          columns: ''
        },
        spacing: {
          margin: '',
          padding: '',
          gap: ''
        },
        design: {
          background: '',
          color: '',
          border: '',
          border_radius: '',
          box_shadow: '',
          style: ''
        },
        behavior: {
          url: '',
          target: '_self',
          zoom: ''
        },
        advanced: {
          css_class: '',
          css_id: '',
          custom_style: ''
        },
        custom_css: ''
      },
      children: []
    }
  });
})();
