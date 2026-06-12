(function () {
  const cards = document.querySelectorAll('.tf-media-card[data-media-id]');
  const targets = document.querySelectorAll('[data-category-drop]');

  if (!cards.length || !targets.length) {
    return;
  }

  let draggedId = null;

  function notify(text, ok) {
    let box = document.getElementById('tf-media-dnd-status');

    if (!box) {
      box = document.createElement('div');
      box.id = 'tf-media-dnd-status';
      box.className = 'tf-media-dnd-status';
      document.body.appendChild(box);
    }

    box.textContent = text;
    box.className = 'tf-media-dnd-status ' + (ok ? 'success' : 'error') + ' show';

    window.setTimeout(() => {
      box.classList.remove('show');
    }, 1800);
  }

  cards.forEach(card => {
    card.addEventListener('dragstart', event => {
      draggedId = card.dataset.mediaId;
      card.classList.add('dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', draggedId);
    });

    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
      draggedId = null;
      targets.forEach(target => target.classList.remove('drop-hover'));
    });
  });

  targets.forEach(target => {
    target.addEventListener('dragover', event => {
      event.preventDefault();
      target.classList.add('drop-hover');
      event.dataTransfer.dropEffect = 'move';
    });

    target.addEventListener('dragleave', () => {
      target.classList.remove('drop-hover');
    });

    target.addEventListener('drop', event => {
      event.preventDefault();
      target.classList.remove('drop-hover');

      const id = draggedId || event.dataTransfer.getData('text/plain');
      const category = target.dataset.categoryDrop || '';

      if (!id) {
        notify('Kein Medium erkannt.', false);
        return;
      }

      const data = new FormData();
      data.append('id', id);
      data.append('category', category);

      fetch('/api/media/set-category.php', {
        method: 'POST',
        body: data
      })
        .then(response => response.json())
        .then(json => {
          if (!json.ok) {
            throw new Error(json.error || 'Kategorie konnte nicht gespeichert werden.');
          }

          notify('Kategorie gespeichert.', true);

          window.setTimeout(() => {
            window.location.reload();
          }, 500);
        })
        .catch(error => {
          notify(error.message, false);
        });
    });
  });
})();