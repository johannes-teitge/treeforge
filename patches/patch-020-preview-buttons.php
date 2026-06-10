<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 020
 * Preview Buttons
 *
 * - ergänzt Preview/Live Buttons im Workflow-Bereich
 * - Published: Live ansehen + Draft bearbeiten
 * - Draft: Draft Preview + In Review senden
 * - Review: Review Preview + Publish/Zurück
 * - Archive: Archiv Preview aktuell über Explorer-Ansicht + Restore
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

    $log('Patch 020 Preview Buttons gestartet');

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (!file_exists($rendererFile)) {
        throw new RuntimeException('ExplorerRenderer.php nicht gefunden.');
    }

    $renderer = file_get_contents($rendererFile);

    $old = <<<'PHP'
    protected function workflowActions(string $workspace, ?string $selectedArchiveVersion): string
    {
        if ($selectedArchiveVersion !== null && $selectedArchiveVersion !== '') {
            $version = htmlspecialchars($selectedArchiveVersion, ENT_QUOTES, 'UTF-8');

            return '<button type="button" class="tf-workflow-button danger" data-archive-restore="' . $version . '">Archivversion wiederherstellen</button><a class="tf-workflow-link secondary" href="/explorer?workspace=published">Zurück zu Published</a>';
        }

        return match ($workspace) {
            'draft' => '<button type="button" class="tf-workflow-button" data-workflow-action="send_to_review">In Review senden</button>',
            'review' => '<button type="button" class="tf-workflow-button" data-workflow-action="publish_review">Freigeben & veröffentlichen</button><button type="button" class="tf-workflow-button secondary" data-workflow-action="return_to_draft">Zurück an Draft</button>',
            default => '<a class="tf-workflow-link" href="/explorer?workspace=draft">Draft bearbeiten</a>',
        };
    }
PHP;

    $new = <<<'PHP'
    protected function workflowActions(string $workspace, ?string $selectedArchiveVersion): string
    {
        if ($selectedArchiveVersion !== null && $selectedArchiveVersion !== '') {
            $version = htmlspecialchars($selectedArchiveVersion, ENT_QUOTES, 'UTF-8');

            return ''
                . '<a class="tf-workflow-link preview" href="/explorer?archive=' . $version . '&page=home" target="_blank" rel="noopener">Archiv ansehen</a>'
                . '<button type="button" class="tf-workflow-button danger" data-archive-restore="' . $version . '">Archivversion wiederherstellen</button>'
                . '<a class="tf-workflow-link secondary" href="/explorer?workspace=published">Zurück zu Published</a>';
        }

        return match ($workspace) {
            'published' => ''
                . '<a class="tf-workflow-link preview" href="/" target="_blank" rel="noopener">Live ansehen</a>'
                . '<a class="tf-workflow-link secondary" href="/explorer?workspace=draft">Draft bearbeiten</a>',

            'draft' => ''
                . '<a class="tf-workflow-link preview" href="/?workspace=draft" target="_blank" rel="noopener">Draft Preview</a>'
                . '<button type="button" class="tf-workflow-button" data-workflow-action="send_to_review">In Review senden</button>',

            'review' => ''
                . '<a class="tf-workflow-link preview" href="/?workspace=review" target="_blank" rel="noopener">Review Preview</a>'
                . '<button type="button" class="tf-workflow-button" data-workflow-action="publish_review">Freigeben & veröffentlichen</button>'
                . '<button type="button" class="tf-workflow-button secondary" data-workflow-action="return_to_draft">Zurück an Draft</button>',

            default => '<a class="tf-workflow-link" href="/explorer?workspace=draft">Draft bearbeiten</a>',
        };
    }
PHP;

    if (str_contains($renderer, $old)) {
        $renderer = str_replace($old, $new, $renderer);
    } else {
        $log('Standard workflowActions-Block nicht exakt gefunden. Versuche Regex-Ersetzung.');

        $renderer = preg_replace(
            '#    protected function workflowActions\(string \$workspace, \?string \$selectedArchiveVersion\): string\s*\{.*?\n    \}\n\}#s',
            $new . "\n}",
            $renderer
        );
    }

    $renderer = preg_replace(
        '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
        '<script src="/assets/js/explorer.js?v=020"></script>',
        $renderer
    );

    $write($rendererFile, $renderer);

    $cssFile = $root . '/public/assets/css/explorer.css';

    if (file_exists($cssFile)) {
        $css = file_get_contents($cssFile);

        if (!str_contains($css, '.tf-workflow-link.preview')) {
            $css .= <<<'CSS'

.tf-workflow-link.preview {
  background: var(--tf-gold);
  color: #fff;
}

.tf-workflow-link.preview:hover {
  filter: brightness(.95);
}
CSS;

            $write($cssFile, $css);
        }
    }

    $write($root . '/docs/preview-buttons.md', <<<'MD'
# Preview Buttons

Patch 020 ergänzt Vorschau-Buttons im Explorer.

## Buttons je Workspace

```text
Published:
- Live ansehen
- Draft bearbeiten

Draft:
- Draft Preview
- In Review senden

Review:
- Review Preview
- Freigeben & veröffentlichen
- Zurück an Draft

Archive:
- Archiv ansehen
- Archivversion wiederherstellen
```

## Preview URLs

```text
/                  = Published
/?workspace=draft  = Draft Preview
/?workspace=review = Review Preview
```

## Warum wichtig?

Nach dem Bearbeiten kann sofort kontrolliert werden, wie die Seite im jeweiligen Workspace aussieht.

MD);

    $log('Patch 020 Preview Buttons fertig');
};
