<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 065
 * Media Drag & Drop Categories
 *
 * Ziel:
 * - Medienkarten per Drag & Drop auf Kategorien links ziehen
 * - AJAX Endpoint speichert Kategorie in Meta JSON
 * - "Nicht einsortiert" als Drop-Ziel
 * - Nach Drop kurze Rückmeldung und Reload
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

    $log('Patch 065 Media Drag & Drop Categories gestartet');

    $write($root . '/public/api/media/set-category.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\Media\MediaCategoryRepository;
use TreeForge\Modules\Media\MediaRepository;

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 3);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Nur POST erlaubt.');
    }

    $id = trim((string)($_POST['id'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));

    if ($id === '') {
        throw new RuntimeException('Media-ID fehlt.');
    }

    $repo = new MediaRepository($root);
    $categories = new MediaCategoryRepository($root);

    $item = $repo->findById($id);

    if (!$item) {
        throw new RuntimeException('Medium nicht gefunden.');
    }

    if ($category !== '' && !$categories->find($category)) {
        throw new RuntimeException('Kategorie nicht gefunden.');
    }

    $item['category'] = $category;
    $repo->save($item);

    echo json_encode([
        'ok' => true,
        'id' => $id,
        'category' => $category,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
PHP);

    $mediaIndex = $root . '/public/admin/media/index.php';

    if (file_exists($mediaIndex)) {
        $content = file_get_contents($mediaIndex);

        if (!str_contains($content, 'data-category-drop')) {
            $content = str_replace(
                '<a class="\' . ($filter === \'_none\' ? \'active\' : \'\') . \'" href="/admin/media/?category=_none"><span>Nicht einsortiert</span><span class="tf-media-count">\' . e($counts[\'\'] ?? 0) . \'</span></a>',
                '<a class="\' . ($filter === \'_none\' ? \'active\' : \'\') . \'" href="/admin/media/?category=_none" data-category-drop=""><span>Nicht einsortiert</span><span class="tf-media-count">\' . e($counts[\'\'] ?? 0) . \'</span></a>',
                $content
            );

            $content = str_replace(
                '<a class="\' . ($filter === $id ? \'active\' : \'\') . \'" href="/admin/media/?category=\' . e($id) . \'">',
                '<a class="\' . ($filter === $id ? \'active\' : \'\') . \'" href="/admin/media/?category=\' . e($id) . \'" data-category-drop="\' . e($id) . \'">',
                $content
            );
        }

        if (!str_contains($content, 'data-media-id')) {
            $content = str_replace(
                '<article class="tf-media-card">',
                '<article class="tf-media-card" draggable="true" data-media-id="\' . e((string)($item[\'id\'] ?? \'\')) . \'">',
                $content
            );
        }

        if (!str_contains($content, '/assets/js/media-categories-dnd.js')) {
            $content = str_replace(
                "echo '<script src=\"/assets/js/media-upload.js\"></script>';",
                "echo '<script src=\"/assets/js/media-upload.js\"></script>';\n"
                . "echo '<script src=\"/assets/js/media-categories-dnd.js\"></script>';",
                $content
            );
        }

        $write($mediaIndex, $content);
    }

    $write($root . '/public/assets/js/media-categories-dnd.js', <<<'JS'
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
JS);

    $cssFile = $root . '/public/assets/css/media.css';
    $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';

    if (!str_contains($css, 'PATCH 065 MEDIA DND CATEGORIES')) {
        $css .= <<<'CSS'

/* PATCH 065 MEDIA DND CATEGORIES */

.tf-media-card[draggable="true"] {
  cursor: grab;
}

.tf-media-card.dragging {
  opacity: .55;
  cursor: grabbing;
  outline: 2px dashed var(--tf-color-secondary, #E2A900);
  outline-offset: 4px;
}

.tf-media-filter a[data-category-drop] {
  position: relative;
}

.tf-media-filter a.drop-hover {
  background: var(--tf-state-warning-bg, #FFF3D7);
  border-color: var(--tf-color-secondary, #E2A900);
  box-shadow: 0 0 0 .18rem var(--tf-focus-ring, rgba(226,169,0,.22));
}

.tf-media-filter a.drop-hover::after {
  content: "hier ablegen";
  position: absolute;
  right: .65rem;
  bottom: -.95rem;
  font-size: .7rem;
  color: var(--tf-state-warning-text, #7A5400);
  background: var(--tf-bg-card, #FFFFFF);
  border: 1px solid var(--tf-border-default, #D7DDE2);
  border-radius: 999px;
  padding: .1rem .4rem;
  z-index: 2;
}

.tf-media-dnd-status {
  position: fixed;
  right: 1.25rem;
  bottom: 1.25rem;
  z-index: 9999;
  padding: .75rem 1rem;
  border-radius: var(--tf-radius-md, .75rem);
  border: 1px solid var(--tf-border-default, #D7DDE2);
  background: var(--tf-bg-card, #FFFFFF);
  color: var(--tf-text-default, #071725);
  box-shadow: var(--tf-shadow-lg, 0 18px 40px rgba(0,0,0,.14));
  opacity: 0;
  transform: translateY(10px);
  pointer-events: none;
  transition: opacity .18s ease, transform .18s ease;
}

.tf-media-dnd-status.show {
  opacity: 1;
  transform: translateY(0);
}

.tf-media-dnd-status.success {
  border-color: var(--tf-state-success-border, #BFE8CC);
  color: var(--tf-state-success-text, #15713A);
}

.tf-media-dnd-status.error {
  border-color: var(--tf-state-danger-border, #FFB8B8);
  color: var(--tf-state-danger-text, #C62828);
}
CSS;

        $write($cssFile, $css);
    }

    $write($root . '/docs/treeforge/55-media-drag-drop-categories.md', <<<'MD'
# Media Drag & Drop Categories

Patch 065 ergänzt Drag & Drop für Media-Kategorien.

## Verhalten

```text
Medienkarte greifen
→ auf Kategorie links ziehen
→ Kategorie wird in Meta JSON gespeichert
→ Seite lädt neu
```

## Endpoint

```text
/api/media/set-category.php
```

## Drop-Ziele

```text
Nicht einsortiert
alle Kategorien
```

## Speichert in

```json
{
  "category": "blog"
}
```
MD);

    $log('Patch 065 Media Drag & Drop Categories fertig');
};
