(function () {
  function normalize(text) {
    return String(text || '').replace(/\s+/g, ' ').trim().toLowerCase();
  }

  function findFieldByLabel(labelText) {
    const wanted = normalize(labelText);
    const labels = Array.from(document.querySelectorAll('label'));

    for (const label of labels) {
      const clone = label.cloneNode(true);
      clone.querySelectorAll('input, textarea, select, button').forEach(node => node.remove());
      const text = normalize(clone.textContent);

      if (text === wanted || text.includes(wanted)) {
        const input = label.querySelector('input[type="text"], input:not([type]), textarea');

        if (input) {
          return { label, input };
        }
      }
    }

    // Fallback: suche Überschrift/Textnode und nächstes Textfeld
    const all = Array.from(document.querySelectorAll('body *'));

    for (const node of all) {
      if (normalize(node.textContent) === wanted) {
        let next = node.nextElementSibling;

        while (next) {
          const input = next.matches && next.matches('input[type="text"], input:not([type]), textarea')
            ? next
            : next.querySelector && next.querySelector('input[type="text"], input:not([type]), textarea');

          if (input) {
            return { label: input.closest('label') || input.parentElement, input };
          }

          next = next.nextElementSibling;
        }
      }
    }

    return null;
  }

  function enhance(labelText, id) {
    const found = findFieldByLabel(labelText);

    if (!found || !found.input || found.input.dataset.mediaPickerEnhanced === '1') {
      return;
    }

    const input = found.input;
    input.dataset.mediaPickerEnhanced = '1';

    if (!input.id) {
      input.id = id;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'tf-media-picker-row';

    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'tf-admin-button secondary';
    button.textContent = 'Auswählen';
    wrapper.appendChild(button);

    const preview = document.createElement('div');
    preview.className = 'tf-media-picker-preview';
    wrapper.parentNode.insertBefore(preview, wrapper.nextSibling);

    button.addEventListener('click', function () {
      if (!window.TreeForgeMediaPicker) {
        alert('Media Picker ist nicht geladen.');
        return;
      }

      window.TreeForgeMediaPicker.open(function (media) {
        input.value = media.id || media.relative_path || media.url || '';
        input.dispatchEvent(new Event('change', { bubbles: true }));

        if (media.preview_url) {
          preview.innerHTML = '<img src="' + media.preview_url + '" alt="">';
        } else {
          preview.innerHTML = '';
        }
      });
    });
  }

  function boot() {
    enhance('OG Image', 'tf_og_image');
    enhance('Featured Image', 'tf_featured_image');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();