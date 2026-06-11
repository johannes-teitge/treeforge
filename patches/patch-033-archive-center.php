<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 033
 * Archive Center
 *
 * Ziel:
 * - Explorer zeigt nur noch die letzten 5 Archivversionen
 * - Link "Alle Archive anzeigen" im Explorer
 * - Neue Route /archives
 * - Archivliste mit Suche, Datumsfilter und Page-Filter
 * - Archiv ansehen
 * - Archivversion wiederherstellen
 *
 * Dateien:
 * - app/Modules/Archives/ArchivesController.php
 * - app/Modules/Archives/ArchivesRenderer.php
 * - public/archives/index.php
 * - public/assets/css/archives.css
 * - app/Modules/Explorer/ExplorerRenderer.php
 * - docs/archive-center.md
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

    $log('Patch 033 Archive Center gestartet');

    $write($root . '/app/Modules/Archives/ArchivesController.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Archives;

use TreeForge\Core\ArchiveManager;

class ArchivesController
{
    public function __construct(
        protected string $root
    ) {
    }

    public function handle(): string
    {
        $pageId = (string)($_GET['page'] ?? 'home');
        $query = trim((string)($_GET['q'] ?? ''));
        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo = trim((string)($_GET['date_to'] ?? ''));

        $archive = new ArchiveManager($this->root);
        $versions = $archive->getVersions($pageId);

        $versions = $this->filterVersions($versions, $pageId, $query, $dateFrom, $dateTo);

        return (new ArchivesRenderer())->render([
            'page' => $pageId,
            'q' => $query,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'versions' => $versions,
            'total' => count($versions),
        ]);
    }

    protected function filterVersions(array $versions, string $pageId, string $query, string $dateFrom, string $dateTo): array
    {
        $fromTimestamp = $this->dateInputToTimestamp($dateFrom, false);
        $toTimestamp = $this->dateInputToTimestamp($dateTo, true);
        $queryLower = mb_strtolower($query);

        return array_values(array_filter($versions, function (array $version) use ($pageId, $queryLower, $fromTimestamp, $toTimestamp): bool {
            $id = (string)($version['version'] ?? '');
            $createdAt = (string)($version['created_at'] ?? '');
            $format = (string)($version['format'] ?? '');

            if ($queryLower !== '') {
                $haystack = mb_strtolower($id . ' ' . $createdAt . ' ' . $format . ' ' . $pageId);

                if (!str_contains($haystack, $queryLower)) {
                    return false;
                }
            }

            $versionTimestamp = $this->versionToTimestamp($id);

            if ($fromTimestamp !== null && $versionTimestamp !== null && $versionTimestamp < $fromTimestamp) {
                return false;
            }

            if ($toTimestamp !== null && $versionTimestamp !== null && $versionTimestamp > $toTimestamp) {
                return false;
            }

            return true;
        }));
    }

    protected function dateInputToTimestamp(string $date, bool $endOfDay): ?int
    {
        if ($date === '') {
            return null;
        }

        $time = strtotime($date . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));

        return $time === false ? null : $time;
    }

    protected function versionToTimestamp(string $version): ?int
    {
        $date = \DateTime::createFromFormat('Y-m-d-His', $version);

        if (!$date instanceof \DateTime) {
            return null;
        }

        return $date->getTimestamp();
    }
}
PHP);

    $write($root . '/app/Modules/Archives/ArchivesRenderer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Archives;

class ArchivesRenderer
{
    public function render(array $data): string
    {
        $page = htmlspecialchars((string)($data['page'] ?? 'home'), ENT_QUOTES, 'UTF-8');
        $q = htmlspecialchars((string)($data['q'] ?? ''), ENT_QUOTES, 'UTF-8');
        $dateFrom = htmlspecialchars((string)($data['date_from'] ?? ''), ENT_QUOTES, 'UTF-8');
        $dateTo = htmlspecialchars((string)($data['date_to'] ?? ''), ENT_QUOTES, 'UTF-8');
        $total = (int)($data['total'] ?? 0);

        $rows = $this->rows((array)($data['versions'] ?? []), $page);

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TreeForge Archive</title>
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/svg+xml" href="/assets/brand/treeforge-icon.svg">
  <link rel="stylesheet" href="/assets/css/brand.css">
  <link rel="stylesheet" href="/assets/css/archives.css">
</head>
<body>
  <header class="tf-archive-header">
    <a href="/explorer" class="tf-brand-link">
      <img src="/assets/brand/treeforge-logo.svg" alt="TreeForge" class="tf-archive-logo">
    </a>
    <div>
      <h1>Archive Center</h1>
      <p>Archivversionen suchen, ansehen und wiederherstellen.</p>
    </div>
  </header>

  <main class="tf-archive-shell">
    <section class="tf-archive-panel">
      <div class="tf-archive-toolbar">
        <div>
          <h2>Archive</h2>
          <p>{$total} Version(en) gefunden</p>
        </div>
        <a class="tf-archive-button secondary" href="/explorer?workspace=published">Zurück zum Explorer</a>
      </div>

      <form class="tf-archive-filter" method="get" action="/archives">
        <label>
          <span>Page</span>
          <input type="text" name="page" value="{$page}">
        </label>

        <label>
          <span>Suche</span>
          <input type="search" name="q" value="{$q}" placeholder="Version, Datum, Format ...">
        </label>

        <label>
          <span>Von</span>
          <input type="date" name="date_from" value="{$dateFrom}">
        </label>

        <label>
          <span>Bis</span>
          <input type="date" name="date_to" value="{$dateTo}">
        </label>

        <div class="tf-archive-filter-actions">
          <button type="submit" class="tf-archive-button">Filtern</button>
          <a href="/archives?page={$page}" class="tf-archive-button secondary">Zurücksetzen</a>
        </div>
      </form>

      <div class="tf-archive-table-wrap">
        <table class="tf-archive-table">
          <thead>
            <tr>
              <th>Version</th>
              <th>Datum</th>
              <th>Format</th>
              <th>Datei</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            {$rows}
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <div class="tf-archive-toast-root" id="tfArchiveToastRoot"></div>

  <script>
    function tfArchiveNotice(type, message) {
      const root = document.getElementById('tfArchiveToastRoot');
      if (!root) return;

      const toast = document.createElement('div');
      toast.className = 'tf-archive-toast ' + type;
      toast.textContent = message;
      root.appendChild(toast);

      setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 280);
      }, 3500);
    }

    document.querySelectorAll('[data-archive-restore]').forEach((button) => {
      button.addEventListener('click', async () => {
        const version = button.getAttribute('data-archive-restore');
        const page = button.getAttribute('data-page') || 'home';

        if (!version) return;

        if (!confirm('Archivversion ' + version + ' wirklich nach Published wiederherstellen?')) {
          return;
        }

        const oldText = button.textContent;
        button.disabled = true;
        button.textContent = 'Stelle wieder her ...';

        try {
          const response = await fetch('/api/archive/restore.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({page: page, version: version})
          });

          const result = await response.json();

          if (!response.ok || !result.ok) {
            throw new Error(result.error || 'Archiv konnte nicht wiederhergestellt werden.');
          }

          tfArchiveNotice('success', result.message || 'Archiv wiederhergestellt.');

          setTimeout(() => {
            window.location.href = '/explorer?workspace=published';
          }, 650);
        } catch (error) {
          tfArchiveNotice('error', error.message);
          button.disabled = false;
          button.textContent = oldText;
        }
      });
    });
  </script>
</body>
</html>
HTML;
    }

    protected function rows(array $versions, string $page): string
    {
        if ($versions === []) {
            return '<tr><td colspan="5" class="tf-archive-empty">Keine Archivversionen gefunden.</td></tr>';
        }

        $html = '';

        foreach ($versions as $version) {
            $id = (string)($version['version'] ?? '');
            $createdAt = (string)($version['created_at'] ?? $id);
            $format = (string)($version['format'] ?? 'archive');
            $file = (string)($version['file'] ?? '');

            $idSafe = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
            $idUrl = rawurlencode($id);
            $pageUrl = rawurlencode($page);
            $createdAtSafe = htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8');
            $formatSafe = htmlspecialchars($format, ENT_QUOTES, 'UTF-8');
            $fileSafe = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');

            $html .= '<tr>';
            $html .= '<td><code>' . $idSafe . '</code></td>';
            $html .= '<td>' . $createdAtSafe . '</td>';
            $html .= '<td><span class="tf-archive-format">' . $formatSafe . '</span></td>';
            $html .= '<td><span class="tf-archive-file" title="' . $fileSafe . '">' . basename($fileSafe) . '</span></td>';
            $html .= '<td class="tf-archive-actions">';
            $html .= '<a class="tf-archive-button small" href="/?archive=' . $idUrl . '&page=' . $pageUrl . '" target="_blank" rel="noopener">Ansehen</a>';
            $html .= '<a class="tf-archive-button small secondary" href="/explorer?archive=' . $idUrl . '&page=' . $pageUrl . '">Im Explorer</a>';
            $html .= '<button type="button" class="tf-archive-button small danger" data-archive-restore="' . $idSafe . '" data-page="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '">Wiederherstellen</button>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        return $html;
    }
}
PHP);

    $write($root . '/public/archives/index.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Core/bootstrap.php';

use TreeForge\Modules\Archives\ArchivesController;

$root = dirname(__DIR__, 2);

echo (new ArchivesController($root))->handle();
PHP);

    $write($root . '/public/assets/css/archives.css', <<<'CSS'
:root {
  --tf-green: #1E3D1C;
  --tf-gold:  #D88A22;
  --tf-dark:  #121A17;
  --tf-light: #F5F3EA;
  --tf-cream: #FFFAF0;
  --tf-border: rgba(23, 63, 53, .16);
}

* { box-sizing: border-box; }

body {
  margin: 0;
  min-height: 100vh;
  background: var(--tf-light);
  color: var(--tf-dark);
  font-family: var(--tf-font-ui, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
}

.tf-archive-header {
  min-height: 86px;
  padding: 1rem 1.5rem;
  background: rgba(255, 250, 240, .92);
  border-bottom: 1px solid var(--tf-border);
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.tf-brand-link { display: inline-flex; }

.tf-archive-logo {
  width: 260px;
  max-width: 42vw;
  height: auto;
  display: block;
}

.tf-archive-header h1 {
  margin: 0;
  font-size: 1.35rem;
  color: var(--tf-green);
}

.tf-archive-header p {
  margin: .15rem 0 0;
  color: #6b746f;
}

.tf-archive-shell {
  padding: 1rem;
}

.tf-archive-panel {
  background: var(--tf-cream);
  border: 1px solid var(--tf-border);
  border-radius: 1rem;
  padding: 1rem;
  box-shadow: 0 1rem 2.8rem rgba(18, 26, 23, .05);
}

.tf-archive-toolbar {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.tf-archive-toolbar h2 {
  margin: 0;
  font-size: 1rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--tf-green);
}

.tf-archive-toolbar p {
  margin: .25rem 0 0;
  color: #6b746f;
  font-weight: 700;
}

.tf-archive-filter {
  display: grid;
  grid-template-columns: 160px minmax(220px, 1fr) 160px 160px auto;
  gap: .75rem;
  align-items: end;
  background: #fff;
  border: 1px solid rgba(30, 61, 28, .1);
  border-radius: 1rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.tf-archive-filter label {
  display: grid;
  gap: .35rem;
  color: var(--tf-green);
  font-weight: 800;
  font-size: .9rem;
}

.tf-archive-filter input {
  width: 100%;
  border: 1px solid rgba(23, 63, 53, .22);
  border-radius: .75rem;
  padding: .65rem .75rem;
  font: inherit;
  background: var(--tf-cream);
  color: var(--tf-dark);
}

.tf-archive-filter-actions,
.tf-archive-actions {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}

.tf-archive-button {
  border: 0;
  border-radius: .75rem;
  background: var(--tf-green);
  color: #fff;
  font-weight: 800;
  padding: .65rem .85rem;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
  font: inherit;
}

.tf-archive-button.secondary {
  background: #fff;
  color: var(--tf-green);
  border: 1px solid rgba(30, 61, 28, .18);
}

.tf-archive-button.danger {
  background: #8a3b14;
}

.tf-archive-button.small {
  padding: .45rem .65rem;
  border-radius: .65rem;
  font-size: .86rem;
}

.tf-archive-button:disabled {
  opacity: .55;
  cursor: not-allowed;
}

.tf-archive-table-wrap {
  overflow: auto;
  border-radius: 1rem;
  border: 1px solid rgba(30, 61, 28, .12);
}

.tf-archive-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}

.tf-archive-table th,
.tf-archive-table td {
  text-align: left;
  padding: .85rem;
  border-bottom: 1px solid rgba(30, 61, 28, .09);
  vertical-align: middle;
}

.tf-archive-table th {
  color: var(--tf-green);
  background: rgba(30, 61, 28, .06);
  font-size: .82rem;
  text-transform: uppercase;
  letter-spacing: .07em;
}

.tf-archive-table tr:last-child td {
  border-bottom: 0;
}

.tf-archive-format {
  border-radius: 999px;
  background: rgba(216, 138, 34, .16);
  color: #8a5411;
  font-size: .8rem;
  font-weight: 800;
  padding: .25rem .55rem;
}

.tf-archive-file {
  display: inline-block;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #5f6b65;
}

.tf-archive-empty {
  text-align: center;
  color: #6b746f;
  padding: 2rem !important;
  font-weight: 700;
}

.tf-archive-toast-root {
  position: fixed;
  right: 1rem;
  bottom: 1rem;
  z-index: 9999;
  display: grid;
  gap: .6rem;
  max-width: min(420px, calc(100vw - 2rem));
}

.tf-archive-toast {
  padding: .85rem 1rem;
  border-radius: .9rem;
  color: #fff;
  font-weight: 800;
  box-shadow: 0 .7rem 2rem rgba(0, 0, 0, .18);
  opacity: 1;
  transform: translateY(0);
  transition: opacity .25s ease, transform .25s ease;
  background: var(--tf-green);
}

.tf-archive-toast.error {
  background: #8a3b14;
}

.tf-archive-toast.hide {
  opacity: 0;
  transform: translateY(.4rem);
}

@media (max-width: 1050px) {
  .tf-archive-filter {
    grid-template-columns: 1fr 1fr;
  }

  .tf-archive-filter-actions {
    grid-column: 1 / -1;
  }
}

@media (max-width: 720px) {
  .tf-archive-toolbar {
    flex-direction: column;
  }

  .tf-archive-filter {
    grid-template-columns: 1fr;
  }

  .tf-archive-logo {
    max-width: 60vw;
  }
}
CSS);

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        if (!str_contains($renderer, 'Alle Archive anzeigen')) {
            $newArchiveLinks = <<<'PHP'
    protected function archiveLinks(array $archiveVersions, ?string $selectedArchiveVersion): string
    {
        if ($archiveVersions === []) {
            return ''
                . '<div class="tf-archive-empty">Noch keine Archivversionen.</div>'
                . '<a class="tf-archive-link all" href="/archives?page=home"><span>📦</span><span>Archive Center öffnen</span></a>';
        }

        $visibleVersions = array_slice($archiveVersions, 0, 5);

        $html = '<div class="tf-archive-list">';

        foreach ($visibleVersions as $version) {
            $id = (string)$version['version'];
            $label = (string)($version['created_at'] ?? $id);
            $active = $selectedArchiveVersion === $id ? ' active' : '';

            $html .= '<a class="tf-archive-link' . $active . '" href="/explorer?archive=' . rawurlencode($id) . '&page=home">';
            $html .= '<span>🕘</span><span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
            $html .= '</a>';
        }

        if (count($archiveVersions) > 5) {
            $html .= '<div class="tf-archive-more">+' . (count($archiveVersions) - 5) . ' weitere Archivversion(en)</div>';
        }

        $html .= '<a class="tf-archive-link all" href="/archives?page=home"><span>📦</span><span>Alle Archive anzeigen</span></a>';
        $html .= '</div>';

        return $html;
    }

PHP;

            $renderer = preg_replace(
                '/    protected function archiveLinks\(array \$archiveVersions, \?string \$selectedArchiveVersion\): string\s*\{.*?\n    \}\n\n    protected function workflowActions/s',
                $newArchiveLinks . '    protected function workflowActions',
                $renderer,
                1,
                $count
            );

            if ($count > 0) {
                $write($rendererFile, $renderer);
            } else {
                $log('Hinweis: archiveLinks-Methode im ExplorerRenderer nicht gefunden');
            }
        } else {
            $log('Archive Center Link im ExplorerRenderer bereits vorhanden');
        }
    }

    $cssFile = $root . '/public/assets/css/explorer.css';

    if (file_exists($cssFile)) {
        $css = file_get_contents($cssFile);

        if (!str_contains($css, '.tf-archive-more')) {
            $css .= <<<'CSS'

.tf-archive-more {
  margin: .45rem 0;
  padding: .55rem .75rem;
  border-radius: .75rem;
  background: rgba(30, 61, 28, .06);
  color: #5f6b65;
  font-size: .85rem;
  font-weight: 800;
}

.tf-archive-link.all {
  margin-top: .5rem;
  background: rgba(216, 138, 34, .14);
  color: #7a4b0f;
}
CSS;

            $write($cssFile, $css);
        }
    }

    $write($root . '/docs/archive-center.md', <<<'MD'
# Archive Center

Patch 033 ergänzt eine eigene Archivverwaltung.

## Route

```text
/archives
```

## Funktionen

- Explorer zeigt nur noch die letzten 5 Archivversionen
- Link "Alle Archive anzeigen"
- Archivliste mit Suche
- Filter nach Datum
- Page-Filter
- Archiv ansehen
- Archiv im Explorer öffnen
- Archivversion wiederherstellen

## Warum?

Der Explorer bleibt dadurch schlank und fokussiert auf die aktuelle Tree-Bearbeitung.

Das Archive Center übernimmt die Verwaltung alter Versionen.
MD);

    $log('Patch 033 Archive Center fertig');
};
