(function () {
  function params() {
    return new URLSearchParams(window.location.search);
  }

  function currentPage() {
    return params().get('page') || 'home';
  }

  function currentArea() {
    return params().get('area') || '';
  }

  function currentKind() {
    return currentArea() ? 'area' : 'page';
  }

  function currentWorkspace() {
    return params().get('workspace') || 'draft';
  }

  function toast(message, type) {
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

  async function mutate(action, payload) {
    const body = {
      page: currentPage(),
      area: currentArea(),
      kind: currentKind(),
      workspace: currentWorkspace(),
      action: action,
      payload: payload || {}
    };

    const response = await fetch('/api/explorer-v2/mutate.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    });

    const json = await response.json().catch(() => null);

    if (!response.ok || !json || json.ok !== true) {
      throw new Error((json && json.error) ? json.error : 'Mutation fehlgeschlagen.');
    }

    return json;
  }

  function reloadKeepingState() {
    const url = new URL(window.location.href);

    if (!url.searchParams.get('workspace')) {
      url.searchParams.set('workspace', currentWorkspace());
    }

    window.location.href = url.toString();
  }

  window.TreeForgeV2Mutations = {
    mutate,
    toast,
    reloadKeepingState,
    currentPage,
    currentArea,
    currentKind,
    currentWorkspace
  };
})();