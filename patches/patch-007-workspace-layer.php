<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 007
 * Workspace Layer Foundation
 *
 * Führt Workspace-Ebenen ein:
 * - published = öffentliche Seite
 * - draft     = Arbeitsversion
 * - review    = Freigabeversion
 * - archive   = alte Versionen
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

    $copy = function (string $source, string $target) use ($log): void {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        if (!file_exists($source)) {
            $log("Quelle fehlt, übersprungen: {$source}");
            return;
        }

        if (file_exists($target)) {
            copy($target, $target . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$target}");
        }

        copy($source, $target);
        $log("Datei kopiert: {$source} -> {$target}");
    };

    $mkdir = function (string $dir) use ($log): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            $log("Ordner erstellt: {$dir}");
        } else {
            $log("Ordner vorhanden: {$dir}");
        }
    };

    $log('Patch 007 Workspace Layer gestartet');

    $mkdir($root . '/storage/workspaces/published/pages');
    $mkdir($root . '/storage/workspaces/draft/pages');
    $mkdir($root . '/storage/workspaces/review/pages');
    $mkdir($root . '/storage/workspaces/archive');

    $copy($root . '/storage/pages/home.json', $root . '/storage/workspaces/published/pages/home.json');
    $copy($root . '/storage/pages/home.json', $root . '/storage/workspaces/draft/pages/home.json');

    $write($root . '/app/Core/Workspace.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class Workspace
{
    public const PUBLISHED = 'published';
    public const DRAFT = 'draft';
    public const REVIEW = 'review';
    public const ARCHIVE = 'archive';

    protected string $root;
    protected string $name;

    public function __construct(string $root, string $name)
    {
        $allowed = [
            self::PUBLISHED,
            self::DRAFT,
            self::REVIEW,
            self::ARCHIVE,
        ];

        if (!in_array($name, $allowed, true)) {
            throw new RuntimeException("Invalid workspace: {$name}");
        }

        $this->root = rtrim($root, '/\\');
        $this->name = $name;
    }

    public static function published(string $root): self
    {
        return new self($root, self::PUBLISHED);
    }

    public static function draft(string $root): self
    {
        return new self($root, self::DRAFT);
    }

    public static function review(string $root): self
    {
        return new self($root, self::REVIEW);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->root . '/storage/workspaces/' . $this->name;
    }

    public function pagePath(string $pageId): string
    {
        return $this->path() . '/pages/' . $pageId . '.json';
    }

    public function hasPage(string $pageId): bool
    {
        return file_exists($this->pagePath($pageId));
    }

    public function loadPage(string $pageId): Page
    {
        $file = $this->pagePath($pageId);

        if (!file_exists($file)) {
            throw new RuntimeException("Page not found in workspace {$this->name}: {$pageId}");
        }

        return new Page($file);
    }

    public function savePage(string $pageId, array $data): void
    {
        $file = $this->pagePath($pageId);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function publish(string $pageId): void
    {
        $draftFile = self::draft($this->root)->pagePath($pageId);
        $publishedFile = self::published($this->root)->pagePath($pageId);

        if (!file_exists($draftFile)) {
            throw new RuntimeException("Draft page not found: {$pageId}");
        }

        if (file_exists($publishedFile)) {
            $archiveDir = $this->root . '/storage/workspaces/archive/' . date('Y-m-d-His');

            if (!is_dir($archiveDir)) {
                mkdir($archiveDir, 0775, true);
            }

            copy($publishedFile, $archiveDir . '/' . $pageId . '.json');
        }

        if (!is_dir(dirname($publishedFile))) {
            mkdir(dirname($publishedFile), 0775, true);
        }

        copy($draftFile, $publishedFile);
    }
}
PHP);

    $write($root . '/public/index.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/bootstrap.php';

use TreeForge\Core\Config;
use TreeForge\Core\Workspace;
use TreeForge\Renderer\HtmlRenderer;

$root = dirname(__DIR__);

$config = new Config($root . '/storage/config/app.json');

/**
 * Standard: öffentliche Website liest nur published.
 * Preview lokal: ?workspace=draft oder ?workspace=review
 */
$workspaceName = $_GET['workspace'] ?? Workspace::PUBLISHED;

$workspace = new Workspace($root, $workspaceName);
$page = $workspace->loadPage('home');

$renderer = new HtmlRenderer();

echo $renderer->render($page, $config);
PHP);

    $write($root . '/docs/workflow-system.md', <<<'MD'
# TreeForge Workflow System

TreeForge arbeitet mit Workspace-Layern.

## Grundidee

Die öffentliche Website soll stabil bleiben, während Änderungen vorbereitet und freigegeben werden.

```text
published = öffentliche Live-Version
draft     = Arbeitsversion
review    = Freigabeversion
archive   = alte Versionen
```

## Ordnerstruktur

```text
storage/workspaces/
├─ published/
│  └─ pages/
│     └─ home.json
├─ draft/
│  └─ pages/
│     └─ home.json
├─ review/
│  └─ pages/
└─ archive/
```

## Öffentliche Website

Die normale Website liest immer:

```text
storage/workspaces/published/pages/
```

Damit bleiben Änderungen unsichtbar, bis sie freigegeben werden.

## Vorschau

Für lokale Tests kann ein Workspace per URL gewählt werden:

```text
/?workspace=draft
/?workspace=review
```

Später wird daraus ein geschützter Preview-Link mit Token:

```text
/preview/home?token=abc123
```

## Publishing

Beim Publish passiert:

```text
draft → published
```

Die alte Published-Version wird vorher archiviert:

```text
published → archive/YYYY-MM-DD-HHMMSS/
```

## Warum Workspace statt Node-Level-Layer?

Für den Anfang wird die komplette Seite versioniert.

Das ist einfacher, robuster und leichter zu verstehen.

Später kann bei Bedarf Node-Level-Versionierung ergänzt werden.

## Workflow

```text
1. Öffentliche Seite läuft aus published.
2. Redakteur arbeitet in draft.
3. Marketing erhält Preview-Link.
4. Marketing gibt frei.
5. Draft wird published.
6. Alte Published-Version wandert ins Archiv.
```

## Vorteil

- Live-Seite bleibt stabil.
- Änderungen können geprüft werden.
- Freigaben werden möglich.
- Rollback wird möglich.
- Passt perfekt zu Export/Import und Site Packages.

MD);

    $log('Patch 007 Workspace Layer fertig');
};
