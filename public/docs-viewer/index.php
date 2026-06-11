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