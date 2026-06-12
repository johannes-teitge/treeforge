<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 056
 * Fix Media Edit Form CSS
 *
 * Problem:
 * Die Felder im Media-Edit-Formular laufen inline/gedrückt zusammen.
 *
 * Fix:
 * Media-Edit bekommt eigene saubere Form-CSS-Regeln:
 * - Labels als Block/Grid
 * - Inputs/Textareas volle Breite
 * - saubere 2-Spalten-Gruppen
 * - Checkbox separat
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

    $log('Patch 056 Fix Media Edit Form CSS gestartet');

    $cssFile = $root . '/public/assets/css/media.css';

    $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';

    if (!str_contains($css, 'PATCH 056 MEDIA EDIT FORM FIX')) {
        $css .= <<<'CSS'

/* PATCH 056 MEDIA EDIT FORM FIX */

.tf-media-edit-form label {
  display: grid;
  grid-template-columns: 1fr;
  gap: .35rem;
  margin: 0 0 .85rem;
  color: var(--tf-text-default, #071725);
  font-weight: 560;
}

.tf-media-edit-form label span {
  display: block;
}

.tf-media-edit-form input[type="text"],
.tf-media-edit-form input[type="number"],
.tf-media-edit-form textarea,
.tf-media-edit-form select {
  display: block;
  width: 100%;
  min-width: 0;
  border: 1px solid var(--tf-input-border, #D7DDE2);
  border-radius: var(--tf-radius-sm, .5rem);
  padding: .62rem .7rem;
  font: inherit;
  background: var(--tf-input-bg, #FFFFFF);
  color: var(--tf-input-text, #071725);
}

.tf-media-edit-form textarea {
  min-height: 96px;
  resize: vertical;
}

.tf-media-edit-form input:focus,
.tf-media-edit-form textarea:focus,
.tf-media-edit-form select:focus {
  border-color: var(--tf-input-border-focus, #E2A900);
  outline: 0;
  box-shadow: 0 0 0 .18rem var(--tf-focus-ring, rgba(226,169,0,.22));
}

.tf-media-edit-form small {
  display: block;
  color: var(--tf-text-muted, #64727D);
  font-weight: 480;
  line-height: 1.35;
}

.tf-media-edit-form .tf-page-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .85rem;
}

.tf-media-edit-form .tf-check {
  display: flex;
  align-items: center;
  gap: .55rem;
  margin: .4rem 0 1rem;
}

.tf-media-edit-form .tf-check input[type="checkbox"] {
  width: auto;
  min-width: auto;
  margin: 0;
}

.tf-media-edit-form .tf-check span {
  display: inline;
}

@media (max-width: 760px) {
  .tf-media-edit-form .tf-page-grid {
    grid-template-columns: 1fr;
  }
}
CSS;

        $write($cssFile, $css);
    } else {
        $log('Media Edit Form CSS Fix ist bereits vorhanden');
    }

    $write($root . '/docs/treeforge/46-fix-media-edit-form-css.md', <<<'MD'
# Fix Media Edit Form CSS

Patch 056 korrigiert das Formularlayout im Media Editor.

## Problem

Die Eingabefelder liefen inline zusammen.

## Fix

- Labels als Block/Grid
- Inputs/Textareas volle Breite
- saubere 2-Spalten-Gruppen
- Checkbox separat
MD);

    $log('Patch 056 Fix Media Edit Form CSS fertig');
};
