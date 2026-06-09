<?php
declare(strict_types=1);

function render_page(): string
{
    return <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TreeForge CMS</title>
  <link rel="stylesheet" href="/assets/css/treeforge.css">
</head>
<body>
  <main class="tf-start">
    <div class="tf-logo">🌳⚒️</div>
    <h1>TreeForge CMS</h1>
    <p>Structure first. Content grows.</p>
    <small>First patch installed successfully.</small>
  </main>
</body>
</html>
HTML;
}