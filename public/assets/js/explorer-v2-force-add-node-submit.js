(function () {
  function page() {
    return new URLSearchParams(window.location.search).get('page') || 'home';
  }

  function workspace() {
    return new URLSearchParams(window.location.search).get('workspace') || 'draft';
  }

  function toast(message, type) {
    if (window.TreeForgeV2Mutations && typeof window.TreeForgeV2Mutations.toast === 'function') {
      window.TreeForgeV2Mutations.toast(message, type);
      return;
    }

    alert(message);
  }

  function selectedType() {
    const active = document.querySelector('#tfv2AddNodeDialog .tfv2-node-type-card.active');

    if (active && active.dataset.nodeType) {
      return active.dataset.nodeType;
    }

    return '';
  }

  function parentId() {
    const target = document.getElementById('tfv2AddNodeTarget');

    if (!target) {
      return '';
    }

    return String(target.textContent || '')
      .replace(/^Parent:\s*/i, '')
      .trim();
  }

  function typeDefinition(type) {
    if (window.TreeForgeV2NodeTypeRegistry && typeof window.TreeForgeV2NodeTypeRegistry.byType === 'function') {
      return window.TreeForgeV2NodeTypeRegistry.byType(type);
    }

    if (Array.isArray(window.TreeForgeV2NodeTypes)) {
      return window.TreeForgeV2NodeTypes.find(item => item.type === type) || null;
    }

    return null;
  }

  async function submitAdd(button) {
    const type = selectedType();
    const parent = parentId();
    const definition = typeDefinition(type);

    if (!type) {
      toast('Bitte zuerst einen Node-Typ auswählen.', 'error');
      return;
    }

    if (!parent) {
      toast('Parent-Node fehlt.', 'error');
      return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Wird eingefügt...';

    try {
      const response = await fetch('/api/explorer-v2/mutate.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          page: page(),
          workspace: workspace(),
          action: 'add',
          payload: {
            parent_id: parent,
            type: type,
            defaults: definition && definition.defaults ? definition.defaults : {}
          }
        })
      });

      const json = await response.json().catch(() => null);

      if (!response.ok || !json || json.ok !== true) {
        throw new Error((json && json.error) ? json.error : 'Node konnte nicht eingefügt werden.');
      }

      const newNodeId = json && json.result && json.result.node_id ? json.result.node_id : '';
      if (newNodeId) {
        sessionStorage.setItem('tfv2.lastAddedNode', newNodeId);
      }
      localStorage.removeItem('tfv2.collapsedNodes.' + page());
      toast('Node eingefügt: ' + type);

      const url = new URL(window.location.href);
      url.searchParams.set('page', page());
      url.searchParams.set('workspace', 'draft');
      url.searchParams.set('_', String(Date.now()));

      window.setTimeout(() => {
        window.location.href = url.toString();
      }, 2500);
    } catch (error) {
      toast(error.message || 'Node konnte nicht eingefügt werden.', 'error');
      button.disabled = false;
      button.textContent = originalText;
    }
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest('#tfv2AddNodeCreate');

    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    submitAdd(button);
  }, true);
})();