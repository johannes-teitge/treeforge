<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 067
 * Media Picker Modal Foundation
 *
 * Ziel:
 * - wiederverwendbares Media Picker Modal
 * - Picker-Endpoint /admin/media/picker.php
 * - JS API: window.TreeForgeMediaPicker.open(callback)
 * - später nutzbar für ImageNode, Hero, SEO, Social Image, Downloads
 *
 * Noch nicht:
 * - Integration in konkrete Nodes
 * - Mehrfachauswahl
 * - Suche
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

    $log('Patch 067 Media Picker Modal Foundation gestartet');

    $write($root . '/public/admin/media/picker.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\Media\MediaCategoryRepository;
use TreeForge\Modules\Media\MediaRepository;

$root = dirname(__DIR__, 3);
$repo = new MediaRepository($root);
$categories = new MediaCategoryRepository($root);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bytesHuman(int|float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, 2) . ' ' . $units[$i];
}

$filter = (string)($_GET['category'] ?? 'all');
$items = $repo->all();

if ($filter !== 'all') {
    $items = array_values(array_filter($items, static function (array $item) use ($filter): bool {
        $category = (string)($item['category'] ?? '');

        if ($filter === '_none') {
            return $category === '';
        }

        return $category === $filter;
    }));
}

$allItems = $repo->all();
$counts = $categories->counts($allItems);

header('Content-Type: text/html; charset=utf-8');

echo '<div class="tf-picker-shell">';
echo '<aside class="tf-picker-sidebar">';
echo '<h3>Medien</h3>';
echo '<button type="button" class="tf-picker-filter' . ($filter === 'all' ? ' active' : '') . '" data-picker-category="all">Alle <span>' . e(count($allItems)) . '</span></button>';
echo '<button type="button" class="tf-picker-filter' . ($filter === '_none' ? ' active' : '') . '" data-picker-category="_none">Nicht einsortiert <span>' . e($counts[''] ?? 0) . '</span></button>';
echo '<h3>Kategorien</h3>';

foreach ($categories->all() as $category) {
    $id = (string)($category['id'] ?? '');
    echo '<button type="button" class="tf-picker-filter' . ($filter === $id ? ' active' : '') . '" data-picker-category="' . e($id) . '">'
        . e($category['label'] ?? $id)
        . ' <span>' . e($counts[$id] ?? 0) . '</span>'
        . '</button>';
}

echo '</aside>';

echo '<section class="tf-picker-content">';
echo '<div class="tf-picker-toolbar">';
echo '<strong>' . e(count($items)) . ' Medien</strong>';
echo '<span>Medium auswählen und übernehmen.</span>';
echo '</div>';

if ($items === []) {
    echo '<div class="tf-media-empty">Keine Medien gefunden.</div>';
} else {
    echo '<div class="tf-picker-grid">';

    foreach ($items as $item) {
        $url = method_exists($repo, 'publicUrlWithVersion') ? $repo->publicUrlWithVersion($item) : $repo->publicUrl($item);
        $plainUrl = $repo->publicUrl($item);
        $kind = (string)($item['kind'] ?? '');
        $isImage = in_array($kind, ['image', 'vector'], true);

        $preview = $isImage
            ? '<img src="' . e($url) . '" alt="' . e((string)($item['alt'] ?? '')) . '">'
            : '<div class="tf-media-file-preview">' . e(strtoupper((string)($item['extension'] ?? 'FILE'))) . '</div>';

        $payload = [
            'id' => (string)($item['id'] ?? ''),
            'title' => (string)($item['title'] ?? ''),
            'alt' => (string)($item['alt'] ?? ''),
            'filename' => (string)($item['filename'] ?? ''),
            'relative_path' => (string)($item['relative_path'] ?? ''),
            'url' => $plainUrl,
            'preview_url' => $url,
            'kind' => $kind,
            'mime' => (string)($item['mime'] ?? ''),
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'size' => $item['size'] ?? null,
            'category' => (string)($item['category'] ?? ''),
        ];

        echo '<button type="button" class="tf-picker-item" data-media=\'' . e(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '\'>'
            . '<span class="tf-picker-preview">' . $preview . '</span>'
            . '<span class="tf-picker-info">'
            . '<strong>' . e($item['title'] ?? $item['filename'] ?? '') . '</strong>'
            . '<small>' . e($item['filename'] ?? '') . '</small>'
            . '<small>' . e(($item['width'] ?? '-') . ' × ' . ($item['height'] ?? '-') . ' · ' . bytesHuman((int)($item['size'] ?? 0))) . '</small>'
            . '</span>'
            . '</button>';
    }

    echo '</div>';
}

echo '</section>';
echo '</div>';
PHP);

    $write($root . '/public/assets/js/media-picker.js', <<<'JS'
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
JS);

    $cssFile = $root . '/public/assets/css/media.css';
    $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';

    if (!str_contains($css, 'PATCH 067 MEDIA PICKER')) {
        $css .= <<<'CSS'

/* PATCH 067 MEDIA PICKER */

body.tf-picker-open {
  overflow: hidden;
}

.tf-picker-modal {
  position: fixed;
  inset: 0;
  z-index: 99999;
  display: none;
}

.tf-picker-modal.open {
  display: block;
}

.tf-picker-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(7, 23, 37, .58);
}

.tf-picker-dialog {
  position: absolute;
  inset: 4vh 4vw;
  background: var(--tf-bg-app, #F4F6F7);
  border: 1px solid var(--tf-border-default, #D7DDE2);
  border-radius: var(--tf-radius-lg, 1rem);
  box-shadow: 0 28px 90px rgba(0,0,0,.28);
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  overflow: hidden;
}

.tf-picker-head,
.tf-picker-foot {
  background: var(--tf-bg-card, #FFFFFF);
  border-bottom: 1px solid var(--tf-border-default, #D7DDE2);
  padding: .85rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.tf-picker-foot {
  border-top: 1px solid var(--tf-border-default, #D7DDE2);
  border-bottom: 0;
  justify-content: flex-end;
}

.tf-picker-head strong {
  display: block;
  color: var(--tf-text-heading, #071725);
  font-weight: 650;
}

.tf-picker-head span {
  display: block;
  color: var(--tf-text-muted, #64727D);
  font-size: .86rem;
}

.tf-picker-close {
  width: 34px;
  height: 34px;
  border: 1px solid var(--tf-border-default, #D7DDE2);
  background: var(--tf-bg-card, #FFFFFF);
  border-radius: var(--tf-radius-sm, .5rem);
  font-size: 1.35rem;
  line-height: 1;
  cursor: pointer;
}

.tf-picker-body {
  min-height: 0;
  overflow: hidden;
}

.tf-picker-shell {
  height: 100%;
  display: grid;
  grid-template-columns: 250px minmax(0, 1fr);
  min-height: 0;
}

.tf-picker-sidebar {
  background: var(--tf-bg-card, #FFFFFF);
  border-right: 1px solid var(--tf-border-default, #D7DDE2);
  padding: 1rem;
  overflow: auto;
}

.tf-picker-sidebar h3 {
  margin: 0 0 .5rem;
  font-size: .9rem;
  color: var(--tf-text-muted, #64727D);
  text-transform: uppercase;
  letter-spacing: .03em;
}

.tf-picker-filter {
  width: 100%;
  border: 0;
  background: transparent;
  color: var(--tf-text-default, #071725);
  padding: .58rem .65rem;
  border-radius: var(--tf-radius-sm, .5rem);
  display: flex;
  justify-content: space-between;
  gap: .75rem;
  font: inherit;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.tf-picker-filter:hover,
.tf-picker-filter.active {
  background: var(--tf-bg-hover, #EAF1F5);
}

.tf-picker-filter span {
  color: var(--tf-text-muted, #64727D);
}

.tf-picker-content {
  min-width: 0;
  min-height: 0;
  overflow: auto;
  padding: 1rem;
}

.tf-picker-toolbar {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
  color: var(--tf-text-muted, #64727D);
}

.tf-picker-toolbar strong {
  color: var(--tf-text-heading, #071725);
}

.tf-picker-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
  gap: .85rem;
}

.tf-picker-item {
  border: 1px solid var(--tf-border-default, #D7DDE2);
  background: var(--tf-bg-card, #FFFFFF);
  border-radius: var(--tf-radius-md, .75rem);
  overflow: hidden;
  padding: 0;
  cursor: pointer;
  text-align: left;
  box-shadow: var(--tf-shadow-xs, 0 1px 2px rgba(18,26,23,.04));
}

.tf-picker-item:hover {
  border-color: var(--tf-color-secondary, #E2A900);
  box-shadow: 0 0 0 .18rem var(--tf-focus-ring, rgba(226,169,0,.22));
}

.tf-picker-preview {
  aspect-ratio: 4 / 3;
  background: var(--tf-bg-hover, #EAF1F5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.tf-picker-preview img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.tf-picker-info {
  display: block;
  padding: .65rem;
}

.tf-picker-info strong,
.tf-picker-info small {
  display: block;
  overflow-wrap: anywhere;
}

.tf-picker-info strong {
  color: var(--tf-text-heading, #071725);
  font-weight: 620;
  margin-bottom: .15rem;
}

.tf-picker-info small {
  color: var(--tf-text-muted, #64727D);
  font-size: .78rem;
  line-height: 1.35;
}

.tf-picker-loading {
  padding: 2rem;
  color: var(--tf-text-muted, #64727D);
  font-weight: 500;
}

.tf-media-picker-preview img {
  max-width: 220px;
  max-height: 140px;
  object-fit: contain;
  display: block;
  border: 1px solid var(--tf-border-default, #D7DDE2);
  border-radius: var(--tf-radius-sm, .5rem);
  background: var(--tf-bg-hover, #EAF1F5);
}

@media (max-width: 860px) {
  .tf-picker-dialog {
    inset: 1rem;
  }

  .tf-picker-shell {
    grid-template-columns: 1fr;
  }

  .tf-picker-sidebar {
    border-right: 0;
    border-bottom: 1px solid var(--tf-border-default, #D7DDE2);
    max-height: 180px;
  }
}
CSS;

        $write($cssFile, $css);
    }

    $layoutFile = $root . '/app/Admin/AdminLayout.php';

    if (file_exists($layoutFile)) {
        $layout = file_get_contents($layoutFile);

        if (!str_contains($layout, '/assets/js/media-picker.js')) {
            $layout = str_replace(
                '</body>',
                '<script src="/assets/js/media-picker.js"></script></body>',
                $layout
            );

            $write($layoutFile, $layout);
        }
    }

    $write($root . '/docs/treeforge/57-media-picker-modal-foundation.md', <<<'MD'
# Media Picker Modal Foundation

Patch 067 ergänzt einen wiederverwendbaren Media Picker.

## JavaScript API

```js
window.TreeForgeMediaPicker.open(function(media) {
  console.log(media.id);
});
```

## Automatische Nutzung über Attribute

```html
<input id="hero_image" name="hero_image">
<button data-media-picker-target="#hero_image">
  Aus Medienbibliothek auswählen
</button>
```

Optional mit Vorschau:

```html
<div id="hero_preview" class="tf-media-picker-preview"></div>
<button
  data-media-picker-target="#hero_image"
  data-media-picker-preview="#hero_preview">
  Auswählen
</button>
```

## Picker Endpoint

```text
/admin/media/picker.php
```

## Payload

Der Picker liefert:

```json
{
  "id": "...",
  "title": "...",
  "alt": "...",
  "filename": "...",
  "relative_path": "...",
  "url": "...",
  "preview_url": "...",
  "kind": "image",
  "mime": "image/webp",
  "width": 1200,
  "height": 800,
  "size": 123456,
  "category": "hero"
}
```

## Nächste Schritte

- Integration in ImageNode
- Integration in HeroNode
- Integration in Page Settings Social Image
- Suche
- Mehrfachauswahl für GalleryNode
MD);

    $log('Patch 067 Media Picker Modal Foundation fertig');
};
