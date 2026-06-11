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
            $html .= '<a class="tf-archive-button small secondary" href="/api/archive/export-json.php?version=' . $idUrl . '&page=' . $pageUrl . '">JSON Export</a>';
            $html .= '<button type="button" class="tf-archive-button small danger" data-archive-restore="' . $idSafe . '" data-page="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '">Wiederherstellen</button>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        return $html;
    }
}