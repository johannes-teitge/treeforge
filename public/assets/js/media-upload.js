(function () {
  const zone = document.getElementById('tf-media-upload-zone');
  const input = document.getElementById('tf-media-upload-input');
  const select = document.getElementById('tf-media-upload-select');
  const result = document.getElementById('tf-media-upload-result');

  if (!zone || !input || !select || !result) {
    return;
  }

  function message(text, ok) {
    result.textContent = text;
    result.className = 'tf-media-upload-result ' + (ok ? 'success' : 'error');
  }

  function upload(files) {
    if (!files || !files.length) {
      return;
    }

    const data = new FormData();

    Array.from(files).forEach(file => {
      data.append('files[]', file);
    });

    message('Upload läuft...', true);

    fetch('/api/media/upload.php', {
      method: 'POST',
      body: data
    })
      .then(response => response.json())
      .then(json => {
        if (!json.ok) {
          throw new Error(json.error || 'Upload fehlgeschlagen.');
        }

        message(json.files.length + ' Datei(en) hochgeladen. Seite wird neu geladen...', true);

        setTimeout(() => {
          window.location.reload();
        }, 700);
      })
      .catch(error => {
        message(error.message, false);
      });
  }

  select.addEventListener('click', () => input.click());

  input.addEventListener('change', () => {
    upload(input.files);
  });

  zone.addEventListener('dragover', event => {
    event.preventDefault();
    zone.classList.add('dragover');
  });

  zone.addEventListener('dragleave', () => {
    zone.classList.remove('dragover');
  });

  zone.addEventListener('drop', event => {
    event.preventDefault();
    zone.classList.remove('dragover');
    upload(event.dataTransfer.files);
  });
})();