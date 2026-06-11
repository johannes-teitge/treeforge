<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 032
 * Fix Archive Frontend Preview
 *
 * Problem:
 * - Der Button "Archiv ansehen" zeigt im Archivmodus auf /explorer?archive=...
 * - Dadurch öffnet sich wieder der Backend-Explorer statt die Frontend-Ansicht.
 *
 * Fix:
 * - public/index.php kann jetzt ?archive=<version>&page=<pageId> laden
 * - Archivdaten werden für den bestehenden HtmlRenderer temporär als Page-Datei bereitgestellt
 * - Button "Archiv ansehen" zeigt nun auf /?archive=<version>&page=home
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

    $log('Patch 032 Fix Archive Frontend Preview gestartet');

    $indexFile = $root . '/public/index.php';

    if (file_exists($indexFile)) {
        $index = file_get_contents($indexFile);

        if (!str_contains($index, 'use TreeForge\Core\ArchiveManager;')) {
            $index = str_replace(
                "use TreeForge\\Core\\Config;\n",
                "use TreeForge\\Core\\ArchiveManager;\nuse TreeForge\\Core\\Config;\n",
                $index
            );
        }

        if (!str_contains($index, 'Archive Frontend Preview')) {
            $old = <<<'PHP'
/**
 * Standard: öffentliche Website liest nur published.
 * Preview lokal: ?workspace=draft oder ?workspace=review
 */
$workspaceName = $_GET['workspace'] ?? Workspace::PUBLISHED;

$workspace = new Workspace($root, $workspaceName);
$page = $workspace->loadPage('home');

$renderer = new HtmlRenderer();

echo $renderer->render($page, $config);
PHP;

            $new = <<<'PHP'
/**
 * Standard: öffentliche Website liest nur published.
 * Preview lokal: ?workspace=draft oder ?workspace=review
 * Archive Frontend Preview: ?archive=<version>&page=home
 */
$workspaceName = (string)($_GET['workspace'] ?? Workspace::PUBLISHED);
$pageId = (string)($_GET['page'] ?? 'home');
$archiveVersion = (string)($_GET['archive'] ?? '');

if ($archiveVersion !== '') {
    $archive = new ArchiveManager($root);
    $pageData = $archive->loadVersion($pageId, $archiveVersion);

    $previewDir = $root . '/storage/cache/archive-preview';

    if (!is_dir($previewDir)) {
        mkdir($previewDir, 0775, true);
    }

    $previewFile = $previewDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $pageId . '-' . $archiveVersion) . '.json';

    file_put_contents(
        $previewFile,
        json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    $page = new \TreeForge\Core\Page($previewFile);
} else {
    $workspace = new Workspace($root, $workspaceName);
    $page = $workspace->loadPage($pageId);
}

$renderer = new HtmlRenderer();

echo $renderer->render($page, $config);
PHP;

            if (str_contains($index, $old)) {
                $index = str_replace($old, $new, $index);
                $write($indexFile, $index);
            } else {
                $log('Hinweis: public/index.php Zielblock nicht gefunden, keine Änderung vorgenommen');
            }
        }
    }

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        $old = <<<'PHP'
            $version = htmlspecialchars($selectedArchiveVersion, ENT_QUOTES, 'UTF-8');

            return ''
                . '<a class="tf-workflow-link preview" href="/explorer?archive=' . $version . '&page=home" target="_blank" rel="noopener">Archiv ansehen</a>'
                . '<button type="button" class="tf-workflow-button danger" data-archive-restore="' . $version . '">Archivversion wiederherstellen</button>'
                . '<a class="tf-workflow-link secondary" href="/explorer?workspace=published">Zurück zu Published</a>';
PHP;

        $new = <<<'PHP'
            $version = htmlspecialchars($selectedArchiveVersion, ENT_QUOTES, 'UTF-8');
            $versionUrl = rawurlencode($selectedArchiveVersion);

            return ''
                . '<a class="tf-workflow-link preview" href="/?archive=' . $versionUrl . '&page=home" target="_blank" rel="noopener">Archiv ansehen</a>'
                . '<button type="button" class="tf-workflow-button danger" data-archive-restore="' . $version . '">Archivversion wiederherstellen</button>'
                . '<a class="tf-workflow-link secondary" href="/explorer?workspace=published">Zurück zu Published</a>';
PHP;

        if (str_contains($renderer, $old)) {
            $renderer = str_replace($old, $new, $renderer);
            $write($rendererFile, $renderer);
        } else {
            $log('Hinweis: ExplorerRenderer Archiv-Button Zielblock nicht gefunden, keine Änderung vorgenommen');
        }
    }

    $write($root . '/docs/archive-frontend-preview.md', <<<'MD'
# Archive Frontend Preview

Patch 032 behebt die Frontend-Vorschau für Archivversionen.

## Problem

Im Archivmodus zeigte der Button "Archiv ansehen" auf:

```text
/explorer?archive=<version>&page=home
```

Dadurch wurde wieder der Backend-Explorer geöffnet.

## Lösung

Der Button zeigt jetzt auf:

```text
/?archive=<version>&page=home
```

`public/index.php` kann Archivversionen laden und über den bestehenden `HtmlRenderer` ausgeben.

## Technischer Hinweis

Da `HtmlRenderer` aktuell ein `Page`-Objekt erwartet, wird die Archivversion für die Vorschau temporär unter folgendem Pfad gespeichert:

```text
storage/cache/archive-preview/
```

Diese Dateien dienen nur der Vorschau.
MD);

    $log('Patch 032 Fix Archive Frontend Preview fertig');
};
