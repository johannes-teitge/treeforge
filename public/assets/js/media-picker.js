(function () {
  const picker = {
    callback: null,
    modal: null,
    body: null,

    ensure() {
      if (this.modal) {
        return;
      }

      this.modal = document.createElement('div');
      this.modal.className = 'tf-picker-modal';
      this.modal.innerHTML = `
        <div class="tf-picker-backdrop" data-picker-close></div>
        <div class="tf-picker-dialog" role="dialog" aria-modal="true" aria-label="Medienbibliothek">
          <header class="tf-picker-head">
            <div>
              <strong>Medienbibliothek</strong>
              <span>Medium auswählen</span>
            </div>
            <button type="button" class="tf-picker-close" data-picker-close>×</button>
          </header>
          <div class="tf-picker-body">Lade Medien...</div>
          <footer class="tf-picker-foot">
            <button type="button" class="tf-admin-button secondary" data-picker-close>Abbrechen</button>
          </footer>
        </div>
      `;

      document.body.appendChild(this.modal);
      this.body = this.modal.querySelector('.tf-picker-body');

      this.modal.addEventListener('click', event => {
        if (event.target.closest('[data-picker-close]')) {
          this.close();
        }

        const filter = event.target.closest('[data-picker-category]');
        if (filter) {
          event.preventDefault();
          this.load(filter.dataset.pickerCategory || 'all');
        }

        const item = event.target.closest('.tf-picker-item');
        if (item) {
          event.preventDefault();
          const media = JSON.parse(item.dataset.media || '{}');

          if (this.callback) {
            this.callback(media);
          }

          this.close();
        }
      });

      document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && this.modal.classList.contains('open')) {
          this.close();
        }
      });
    },

    open(callback, options) {
      this.ensure();
      this.callback = callback;
      this.modal.classList.add('open');
      document.body.classList.add('tf-picker-open');
      this.load((options && options.category) || 'all');
    },

    close() {
      if (!this.modal) {
        return;
      }

      this.modal.classList.remove('open');
      document.body.classList.remove('tf-picker-open');
      this.callback = null;
    },

    load(category) {
      this.body.innerHTML = '<div class="tf-picker-loading">Medien werden geladen...</div>';

      fetch('/admin/media/picker.php?category=' + encodeURIComponent(category || 'all'), {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => response.text())
        .then(html => {
          this.body.innerHTML = html;
        })
        .catch(error => {
          this.body.innerHTML = '<div class="tf-notice error">' + error.message + '</div>';
        });
    }
  };

  window.TreeForgeMediaPicker = {
    open(callback, options) {
      picker.open(callback, options || {});
    }
  };

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-media-picker-target]');

    if (!trigger) {
      return;
    }

    event.preventDefault();

    const targetSelector = trigger.dataset.mediaPickerTarget;
    const target = document.querySelector(targetSelector);

    window.TreeForgeMediaPicker.open(function (media) {
      if (target) {
        target.value = media.id || media.relative_path || '';
        target.dispatchEvent(new Event('change', { bubbles: true }));
      }

      const previewSelector = trigger.dataset.mediaPickerPreview;
      const preview = previewSelector ? document.querySelector(previewSelector) : null;

      if (preview) {
        preview.innerHTML = media.preview_url
          ? '<img src="' + media.preview_url + '" alt="">'
          : '';
      }
    });
  });
})();