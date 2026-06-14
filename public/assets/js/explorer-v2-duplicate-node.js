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

  function nodeTitle(nodeId) {
    const selector = window.CSS && CSS.escape
      ? '[data-node-id="' + CSS.escape(nodeId) + '"]'
      : '[data-node-id="' + nodeId + '"]';

    const node = document.querySelector(selector);

    if (!node) {
      return nodeId;
    }

    const strong = node.querySelector('strong');

    return strong ? strong.textContent.trim() : nodeId;
  }

  async function mutateDuplicate(nodeId) {
    const response = await fetch('/api/explorer-v2/mutate.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        page: page(),
        workspace: workspace(),
        action: 'duplicate',
        payload: {
          node_id: nodeId
        }
      })
    });

    const json = await response.json().catch(() => null);

    if (!response.ok || !json || json.ok !== true) {
      throw new Error((json && json.error) ? json.error : 'Node konnte nicht dupliziert werden.');
    }

    return json;
  }

  document.addEventListener('click', async function (event) {
    const button = event.target.closest('[data-node-action="duplicate"]');

    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    const nodeId = button.dataset.nodeId || '';

    if (!nodeId) {
      toast('Node-ID fehlt.', 'error');
      return;
    }

    button.disabled = true;
    const title = nodeTitle(nodeId);

    try {
      const json = await mutateDuplicate(nodeId);
      const newNodeId = json && json.result && json.result.node_id ? json.result.node_id : '';

      if (newNodeId) {
        sessionStorage.setItem('tfv2.lastAddedNode', newNodeId);
      }

      localStorage.removeItem('tfv2.collapsedNodes.' + page());
      toast('Node dupliziert: ' + title);

      const url = new URL(window.location.href);
      url.searchParams.set('workspace', 'draft');
      url.searchParams.set('_', String(Date.now()));

      window.setTimeout(() => {
        window.location.href = url.toString();
      }, 1800);
    } catch (error) {
      toast(error.message || 'Node konnte nicht dupliziert werden.', 'error');
      button.disabled = false;
    }
  }, true);
})();