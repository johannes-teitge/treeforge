<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 069
 * Fix Page Settings Media Picker DOM Integration
 *
 * Problem:
 * Patch 068 hat die PHP-Stellen in page-settings/index.php nicht getroffen.
 *
 * Fix:
 * Robuste DOM-Erweiterung per JS:
 * - sucht Labels/Felder "OG Image" und "Featured Image"
 * - fügt Auswählen-Button ein
 * - fügt Vorschau-Container ein
 * - nutzt window.TreeForgeMediaPicker aus Patch 067
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 069 Fix Page Settings Media Picker DOM Integration gestartet');

    $write($root . '/public/assets/js/page-settings-media-picker.js', <<<'JS'
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
JS);

    $layoutFile = $root . '/app/Admin/AdminLayout.php';

    if (file_exists($layoutFile)) {
        $layout = file_get_contents($layoutFile);

        if (!str_contains($layout, '/assets/js/page-settings-media-picker.js')) {
            $layout = str_replace(
                '</body>',
                '<script src="/assets/js/page-settings-media-picker.js"></script></body>',
                $layout
            );

            $write($layoutFile, $layout);
        }
    }

    $cssFile = $root . '/public/assets/css/page-settings.css';
    $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';

    if (!str_contains($css, 'PATCH 069 PAGE SETTINGS MEDIA PICKER DOM')) {
        $css .= <<<'CSS'

/* PATCH 069 PAGE SETTINGS MEDIA PICKER DOM */

.tf-media-picker-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: .5rem;
  align-items: center;
}

.tf-media-picker-row input,
.tf-media-picker-row textarea {
  min-width: 0;
  width: 100%;
}

.tf-media-picker-preview img {
  margin-top: .5rem;
  max-width: 260px;
  max-height: 150px;
  object-fit: contain;
  display: block;
  border: 1px solid var(--tf-border-default, #D7DDE2);
  border-radius: var(--tf-radius-sm, .5rem);
  background: var(--tf-bg-hover, #EAF1F5);
}

@media (max-width: 720px) {
  .tf-media-picker-row {
    grid-template-columns: 1fr;
  }
}
CSS;

        $write($cssFile, $css);
    }

    $write($root . '/docs/treeforge/59-fix-page-settings-media-picker-dom.md', <<<'MD'
# Fix Page Settings Media Picker DOM Integration

Patch 069 ergänzt den Media Picker in Page Settings per DOM-Enhancement.

## Warum?

Die PHP-Struktur der Page Settings konnte durch Patch 068 nicht zuverlässig getroffen werden.

## Fix

JavaScript sucht nach:

```text
OG Image
Featured Image
```

und ergänzt automatisch:

```text
Auswählen Button
Vorschau
```

## Voraussetzung

Patch 067:

```text
window.TreeForgeMediaPicker
```
MD);

    $log('Patch 069 Fix Page Settings Media Picker DOM Integration fertig');
};
