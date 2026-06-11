<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 036
 * Frontend Docs Viewer
 *
 * Ziel:
 * - Markdown-Dokumente aus docs/treeforge im Frontend anzeigen
 * - Übersicht mit Sidebar und Dokumentauswahl
 * - Rendering über league/commonmark, falls vorhanden
 * - Fallback-Renderer, falls CommonMark nicht verfügbar ist
 *
 * Dateien:
 * - public/docs-viewer/index.php
 * - public/assets/css/docs-viewer.css
 * - docs/treeforge/26-docs-viewer.md
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

    $log('Patch 036 Frontend Docs Viewer gestartet');

    $write($root . '/public/docs-viewer/index.php', <<<'PHP'
<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$docsDir = $root . '/docs/treeforge';

$autoload = $root . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

function tf_docs_title(string $filename): string
{
    $name = basename($filename, '.md');
    $name = preg_replace('/^\d+\-/', '', $name);
    $name = str_replace(['-', '_'], ' ', (string)$name);
    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
}

function tf_docs_slug(string $filename): string
{
    return basename($filename, '.md');
}

function tf_docs_safe_doc(?string $doc, array $files): string
{
    if ($files === []) {
        return '';
    }

    $allowed = array_map(static fn(string $file): string => tf_docs_slug($file), $files);

    if ($doc && in_array($doc, $allowed, true)) {
        return $doc;
    }

    return tf_docs_slug($files[0]);
}

function tf_docs_render_markdown(string $markdown): string
{
    if (class_exists(\League\CommonMark\CommonMarkConverter::class)) {
        $converter = new \League\CommonMark\CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return (string)$converter->convert($markdown);
    }

    $escaped = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');

    $escaped = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $escaped);
    $escaped = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $escaped);
    $escaped = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $escaped);
    $escaped = preg_replace('/```([a-zA-Z0-9_-]*)\n(.*?)```/s', '<pre><code>$2</code></pre>', $escaped);
    $escaped = nl2br((string)$escaped);

    return $escaped;
}

$files = glob($docsDir . '/*.md') ?: [];
sort($files, SORT_NATURAL);

$currentDoc = tf_docs_safe_doc($_GET['doc'] ?? null, $files);
$currentFile = $currentDoc !== '' ? $docsDir . '/' . $currentDoc . '.md' : '';

$markdown = $currentFile && file_exists($currentFile)
    ? (string)file_get_contents($currentFile)
    : '# Keine Dokumentation gefunden';

$title = $currentFile ? tf_docs_title($currentFile) : 'TreeForge Dokumentation';
$html = tf_docs_render_markdown($markdown);

$nav = '';
foreach ($files as $file) {
    $slug = tf_docs_slug($file);
    $label = tf_docs_title($file);
    $active = $slug === $currentDoc ? ' active' : '';

    $nav .= '<a class="tf-doc-link' . $active . '" href="/docs-viewer/?doc=' . rawurlencode($slug) . '">'
        . '<span class="tf-doc-index">' . htmlspecialchars(substr($slug, 0, 2), ENT_QUOTES, 'UTF-8') . '</span>'
        . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</a>';
}

?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · TreeForge Docs</title>
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#173F35">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="stylesheet" href="/assets/css/docs-viewer.css">
</head>
<body>
  <header class="tf-doc-header">
    <div>
      <a class="tf-doc-brand" href="/docs-viewer/">TREE<span>FORGE</span> Docs</a>
      <p>Architektur, Konzepte und Roadmap direkt aus <code>docs/treeforge</code>.</p>
    </div>
    <nav class="tf-doc-actions">
      <a href="/explorer">Explorer</a>
      <a href="/archives">Archive</a>
      <a href="https://github.com/johannes-teitge/treeforge" target="_blank" rel="noopener">GitHub</a>
    </nav>
  </header>

  <main class="tf-doc-layout">
    <aside class="tf-doc-sidebar">
      <div class="tf-doc-sidebar-title">Dokumente</div>
      <?= $nav ?>
    </aside>

    <article class="tf-doc-content">
      <div class="tf-doc-current">
        <span><?= htmlspecialchars($currentDoc ?: 'docs', ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?= $html ?>
    </article>
  </main>
</body>
</html>
PHP);

    $write($root . '/public/assets/css/docs-viewer.css', <<<'CSS'
:root {
  --tf-green: #173F35;
  --tf-gold: #D88A22;
  --tf-dark: #121A17;
  --tf-light: #F5F3EA;
  --tf-cream: #FFFAF0;
  --tf-border: rgba(23, 63, 53, .14);
  --tf-muted: #66756e;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  background: var(--tf-light);
  color: var(--tf-dark);
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.tf-doc-header {
  min-height: 88px;
  padding: 1rem 1.4rem;
  background: rgba(255, 250, 240, .94);
  border-bottom: 1px solid var(--tf-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  position: sticky;
  top: 0;
  z-index: 10;
  backdrop-filter: blur(12px);
}

.tf-doc-brand {
  color: var(--tf-green);
  text-decoration: none;
  font-weight: 950;
  letter-spacing: .08em;
  font-size: 1.2rem;
}

.tf-doc-brand span {
  color: var(--tf-gold);
}

.tf-doc-header p {
  margin: .2rem 0 0;
  color: var(--tf-muted);
}

.tf-doc-actions {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}

.tf-doc-actions a {
  display: inline-flex;
  padding: .55rem .75rem;
  border-radius: .75rem;
  background: #fff;
  border: 1px solid var(--tf-border);
  color: var(--tf-green);
  text-decoration: none;
  font-weight: 800;
}

.tf-doc-layout {
  display: grid;
  grid-template-columns: 320px minmax(0, 1fr);
  gap: 1rem;
  padding: 1rem;
}

.tf-doc-sidebar {
  background: var(--tf-cream);
  border: 1px solid var(--tf-border);
  border-radius: 1.1rem;
  padding: .8rem;
  height: calc(100vh - 112px);
  overflow: auto;
  position: sticky;
  top: 104px;
}

.tf-doc-sidebar-title {
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--tf-green);
  font-weight: 900;
  padding: .4rem .55rem .75rem;
}

.tf-doc-link {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .65rem .7rem;
  border-radius: .8rem;
  color: var(--tf-dark);
  text-decoration: none;
  font-weight: 750;
  margin-bottom: .25rem;
}

.tf-doc-link:hover,
.tf-doc-link.active {
  background: rgba(216, 138, 34, .14);
  color: var(--tf-green);
}

.tf-doc-index {
  width: 2.05rem;
  height: 2.05rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: .65rem;
  background: rgba(23, 63, 53, .08);
  color: var(--tf-green);
  font-size: .78rem;
  font-weight: 950;
  flex: 0 0 auto;
}

.tf-doc-content {
  background: var(--tf-cream);
  border: 1px solid var(--tf-border);
  border-radius: 1.1rem;
  padding: clamp(1rem, 3vw, 2.5rem);
  max-width: 1100px;
  box-shadow: 0 1rem 2.8rem rgba(18, 26, 23, .05);
}

.tf-doc-current {
  display: inline-flex;
  margin-bottom: 1rem;
  padding: .35rem .6rem;
  border-radius: 999px;
  background: rgba(23, 63, 53, .08);
  color: var(--tf-green);
  font-size: .82rem;
  font-weight: 900;
}

.tf-doc-content h1 {
  margin: 0 0 1rem;
  color: var(--tf-green);
  font-size: clamp(2rem, 4vw, 3rem);
  line-height: 1.08;
}

.tf-doc-content h2 {
  margin: 2.3rem 0 .8rem;
  color: var(--tf-green);
  font-size: 1.55rem;
}

.tf-doc-content h3 {
  margin: 1.6rem 0 .65rem;
  color: #26473f;
}

.tf-doc-content p,
.tf-doc-content li {
  line-height: 1.68;
  color: #2f3b36;
}

.tf-doc-content a {
  color: var(--tf-green);
  font-weight: 800;
}

.tf-doc-content code {
  background: rgba(23, 63, 53, .08);
  color: #0f2d26;
  border-radius: .35rem;
  padding: .1rem .3rem;
}

.tf-doc-content pre {
  background: #0d1411;
  color: #e8f4ec;
  padding: 1rem;
  border-radius: 1rem;
  overflow: auto;
  line-height: 1.5;
}

.tf-doc-content pre code {
  background: transparent;
  color: inherit;
  padding: 0;
}

.tf-doc-content blockquote {
  border-left: 5px solid var(--tf-gold);
  padding: .2rem 0 .2rem 1rem;
  margin: 1.5rem 0;
  color: #33423b;
}

.tf-doc-content table {
  width: 100%;
  border-collapse: collapse;
  margin: 1.2rem 0;
}

.tf-doc-content th,
.tf-doc-content td {
  border: 1px solid var(--tf-border);
  padding: .65rem;
  text-align: left;
}

.tf-doc-content th {
  background: rgba(23, 63, 53, .06);
  color: var(--tf-green);
}

@media (max-width: 980px) {
  .tf-doc-layout {
    grid-template-columns: 1fr;
  }

  .tf-doc-sidebar {
    position: static;
    height: auto;
    max-height: 360px;
  }

  .tf-doc-header {
    position: static;
    flex-direction: column;
    align-items: flex-start;
  }
}
CSS);

    $write($root . '/docs/treeforge/26-docs-viewer.md', <<<'MD'
# Docs Viewer

Patch 036 ergänzt eine kleine Frontend-Ansicht für die TreeForge-Dokumentation.

## Route

```text
/docs-viewer/
```

## Quelle

Alle Dateien aus:

```text
docs/treeforge/*.md
```

werden automatisch in der Sidebar gelistet.

## Rendering

Wenn `league/commonmark` vorhanden ist, wird CommonMark verwendet.

Falls nicht, gibt es einen einfachen Fallback-Renderer.

## Zweck

Die Architektur-Dokumentation kann direkt im Browser gelesen werden, ohne die Markdown-Dateien einzeln öffnen zu müssen.
MD);

    $log('Patch 036 Frontend Docs Viewer fertig');
};
