<?php
declare(strict_types=1);

namespace TreeForge\Modules\Frontend;

class PageRenderer
{
    public function __construct(protected string $root) {}

    public function render(string $pageId = 'home', string $workspace = 'draft', string $template = 'default'): string
    {
        $pageId = preg_replace('/[^a-z0-9_-]/i', '', $pageId) ?: 'home';
        $workspace = in_array($workspace, ['draft', 'review', 'published'], true) ? $workspace : 'draft';
        $template = preg_replace('/[^a-z0-9_-]/i', '', $template) ?: 'default';

        $page = $this->loadPage($pageId, $workspace);
        $body = (new NodeRenderer($this->root))->render($page);
        return $this->shell($page, $body, $workspace, $template);
    }

    protected function loadPage(string $pageId, string $workspace): array
    {
        $file = $this->root . '/storage/workspaces/' . $workspace . '/pages/' . $pageId . '.json';
        if (!file_exists($file) && $workspace !== 'published') {
            $file = $this->root . '/storage/workspaces/published/pages/' . $pageId . '.json';
        }

        if (!file_exists($file)) {
            return [
                'id' => $pageId,
                'type' => 'RootNode',
                'title' => ucfirst($pageId),
                'status' => 'active',
                'visibility' => 'visible',
                'properties' => [],
                'children' => [[
                    'id' => 'demo_text',
                    'type' => 'TextNode',
                    'title' => 'Demo Text',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'properties' => ['text' => 'TreeForge rendert diese Seite bereits aus JSON.'],
                    'children' => [],
                ]],
            ];
        }

        $json = json_decode((string)file_get_contents($file), true);
        if (!is_array($json)) throw new \RuntimeException('Ungültiges Page JSON: ' . $file);
        return $json;
    }

    protected function shell(array $page, string $body, string $workspace, string $template): string
    {
        $title = htmlspecialchars((string)($page['title'] ?? 'TreeForge'), ENT_QUOTES, 'UTF-8');
        $templateClass = htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
        $workspaceEsc = htmlspecialchars($workspace, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <link rel="stylesheet" href="/assets/css/frontend.css">
</head>
<body class="tf-template-{$templateClass}" data-workspace="{$workspaceEsc}">
  <header class="tf-site-header">
    <div class="tf-site-brand">TreeForge</div>
    <nav class="tf-site-nav">
      <a href="/page.php?page=home&workspace={$workspaceEsc}">Home</a>
      <a href="/page.php?page=home&workspace={$workspaceEsc}&template=alt">Template Alt</a>
      <a href="/admin/explorer-v2/?page=home&workspace={$workspaceEsc}">Explorer</a>
    </nav>
  </header>
  <main class="tf-site-main">{$body}</main>
  <footer class="tf-site-footer"><small>Rendered by TreeForge · Workspace: {$workspaceEsc}</small></footer>
</body>
</html>
HTML;
    }
}