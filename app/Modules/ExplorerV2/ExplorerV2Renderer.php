<?php
declare(strict_types=1);

namespace TreeForge\Modules\ExplorerV2;

use TreeForge\Core\Areas\AreaManager;
use TreeForge\Core\Pages\PageTreeManager;

class ExplorerV2Renderer
{
    public function render(array $ctx): string
    {
        $pages = $ctx['pages'];
        $areas = $ctx['areas'] ?? null;
        $pageTree = (array)$ctx['pageTree'];
        $areaList = (array)($ctx['areaList'] ?? []);
        $currentPage = (array)$ctx['currentPage'];
        $pageData = (array)$ctx['currentPageData'];
        $pageId = (string)$ctx['currentPageId'];
        $kind = (string)($ctx['currentKind'] ?? 'page');
        $isArea = $kind === 'area';
        $path = (string)$ctx['currentPath'];
        $workspace = (string)$ctx['workspace'];
        $stats = (array)$ctx['workspaceStats'];
        $archives = (array)$ctx['archiveVersions'];
        $settings = (array)$ctx['settings'];
        $siteName = (string)($settings['general']['site_name'] ?? 'TreeForge CMS');
        $pageTitle = (string)($currentPage['title'] ?? $pageId);
        $template = (string)($currentPage['template'] ?? ($isArea ? 'global-area' : 'default'));
        $status = (string)($currentPage['status'] ?? 'draft');
        $json = $this->e(json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        $targetQuery = ($isArea ? 'area=' : 'page=') . rawurlencode($pageId) . '&workspace=' . rawurlencode($workspace);
        $kindLabel = $isArea ? 'Bereich' : 'Seite';
        $previewLink = $isArea ? '/admin/pages/?workspace=' . rawurlencode($workspace) . '#global-areas' : '/?page=' . rawurlencode($pageId) . '&workspace=' . rawurlencode($workspace);
        $settingsLink = $isArea ? '/admin/pages/?workspace=' . rawurlencode($workspace) . '&edit_area=' . rawurlencode($pageId) . '#global-areas' : '/admin/page-settings/?page=' . rawurlencode($pageId);

        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Explorer V2 · ' . $this->e($pageTitle) . '</title><link rel="stylesheet" href="/assets/css/explorer-v2.css">
  <link rel="stylesheet" href="/assets/css/media.css"></head><body>'
            . '<header class="tfv2-topbar"><div class="tfv2-brand">🌳 ' . $this->e($siteName) . '</div><nav>'
            . '<a href="/admin/">Dashboard</a><a href="/admin/pages/">Pages</a><a class="active" href="/admin/explorer-v2/?' . $this->e($targetQuery) . '">Explorer V2</a><a href="/admin/media/">Media</a><a href="/admin/settings/">Settings</a>'
            . '</nav><div class="tfv2-user">Dscho<span>Superadministrator</span></div></header>'
            . '<section class="tfv2-head"><div><h1>Explorer V2</h1><div class="tfv2-meta"><span>' . $this->e($kindLabel) . ': <strong>' . $this->e($pageTitle) . '</strong></span><span>' . $this->e($path) . '</span><span class="tfv2-badge ' . $this->e($status) . '">' . $this->e($status) . '</span><span>Workspace: <strong>' . $this->e($workspace) . '</strong></span><span>Template: ' . $this->e($template) . '</span></div></div>'
            . '<div class="tfv2-head-actions"><a class="tfv2-btn secondary" href="' . $this->e($settingsLink) . '">' . ($isArea ? 'Area Meta' : 'Page Settings') . '</a><a class="tfv2-btn secondary" target="_blank" href="' . $this->e($previewLink) . '">' . ($isArea ? 'Zur Übersicht' : 'Preview') . '</a><button class="tfv2-btn">Speichern</button></div></section>'
            . '<main class="tfv2-shell" id="tfv2Shell"><aside class="tfv2-sidebar"><section class="tfv2-side-card"><header><strong>📄 Pages</strong><a href="/admin/pages/#new-page">+</a></header>' . $this->renderPageTree($pageTree, $pages, $isArea ? '' : $pageId, 0, $workspace) . '</section>'
            . '<section class="tfv2-side-card"><header><strong>🧩 Globale Bereiche</strong><a href="/admin/pages/#global-areas">+</a></header>' . $this->renderAreaList($areaList, $isArea ? $pageId : '', $workspace) . '</section>'
            . '<section class="tfv2-side-card"><header><strong>🌳 Workspaces</strong></header><div class="tfv2-workspaces">' . $this->renderWorkspaces($workspace, $stats, $pageId, $kind) . '</div></section>'
            . '<section class="tfv2-side-card"><header><strong>🗄️ Archive</strong><span>' . count($archives) . '</span></header>' . $this->renderArchives($archives) . '</section>'
            . '<section class="tfv2-side-card"><header><strong>🔍 Suche</strong></header><div class="tfv2-search"><input type="search" placeholder="Seiten oder Bereiche suchen..."></div></section></aside>'
            . '<div class="tfv2-resizer" id="tfv2Resizer" title="Ziehen = Breite ändern, Doppelklick = einklappen"></div>'
            . '<section class="tfv2-content"><div class="tfv2-card"><header class="tfv2-card-head"><div><h2>' . $this->e($pageTitle) . '</h2><p>' . ($isArea ? 'Globale Bereiche werden später per Twig-Funktion eingebunden.' : 'Nur die Nodes der aktuell gewählten Seite werden geladen.') . '</p></div><div class="tfv2-tabs"><button class="active" data-tab="editor">Editor</button><button data-tab="preview">Preview</button><button data-tab="properties">Properties</button><button data-tab="json">JSON</button></div></header>'
            . '<div class="tfv2-main-grid"><section class="tfv2-node-panel"><div class="tfv2-panel-toolbar"><div><strong>Node Tree</strong><span>' . $this->countNodes($pageData) . ' Nodes · ' . $this->e($workspace) . '</span></div><div><button class="tfv2-btn small secondary">Alle auf</button></div></div>' . $this->renderNodeTree($pageData) . '</section>'
            . '<div class="tfv2-node-resizer" id="tfv2NodeResizer" title="Ziehen = Node Tree Breite ändern, Doppelklick = einklappen"></div>

          <section class="tfv2-editor-panel"><header><div><h3>Node Editor</h3><span id="tfv2SelectedNode">Keine Node ausgewählt</span></div><span class="tfv2-badge draft">bearbeitbar</span></header>'
            . '<div class="tfv2-tab-panel active" data-panel="editor"><div class="tfv2-preview-box">Node auswählen oder Editor später anbinden</div><label>Node Titel<input value=""></label><label>Content<textarea placeholder="Editor Foundation"></textarea></label><button class="tfv2-btn secondary" type="button">⛶ Groß öffnen</button></div>'
            . '<div class="tfv2-tab-panel" data-panel="preview"><div class="tfv2-preview-box large">Preview Foundation</div></div>'
            . '<div class="tfv2-tab-panel" data-panel="properties"><dl class="tfv2-props"><dt>' . $this->e($kindLabel) . ' ID</dt><dd>' . $this->e($pageId) . '</dd><dt>Workspace</dt><dd>' . $this->e($workspace) . '</dd><dt>Template</dt><dd>' . $this->e($template) . '</dd></dl></div>'
            . '<div class="tfv2-tab-panel" data-panel="json"><pre>' . $json . '</pre></div><footer><button class="tfv2-btn secondary">Abbrechen</button><button class="tfv2-btn">Eigenschaften übernehmen</button></footer></section></div></div></section></main><script src="/assets/js/media-picker.js"></script>
  <script src="/assets/js/explorer-v2-node-types.js"></script>
  <script src="/assets/js/explorer-v2.js"></script>
  <script src="/assets/js/explorer-v2-mutations.js?v=132"></script>
  <script src="/assets/js/explorer-v2-add-node-dialog.js"></script>
  <script src="/assets/js/explorer-v2-force-add-node-submit.js"></script>
  <script src="/assets/js/explorer-v2-delete-node.js"></script>
  <script src="/assets/js/explorer-v2-duplicate-node.js"></script>
  <script src="/assets/js/explorer-v2-type-editors.js"></script>
  <script src="/assets/js/explorer-v2-property-editor.js"></script>
  <script src="/assets/js/explorer-v2-save-node-properties.js?v=savefix-20260613222718"></script>
</body></html>';
    }

    protected function renderPageTree(array $nodes, PageTreeManager $pages, string $current, int $level = 0, string $workspace = 'draft'): string
    {
        if ($nodes === []) return '<div class="tfv2-empty">Keine Seiten vorhanden.</div>';
        $html = '<ul class="tfv2-page-tree level-' . $level . '">';
        foreach ($nodes as $node) {
            $id = (string)($node['id'] ?? '');
            $active = $id === $current ? ' active' : '';
            $html .= '<li><a class="tfv2-page-link' . $active . '" href="/admin/explorer-v2/?page=' . rawurlencode($id) . '&workspace=' . rawurlencode($workspace) . '"><span>📄 ' . $this->e((string)($node['title'] ?? $id)) . '</span><small>' . $this->e($pages->pathFor($node)) . '</small></a>';
            if (!empty($node['children'])) $html .= $this->renderPageTree((array)$node['children'], $pages, $current, $level + 1, $workspace);
            $html .= '</li>';
        }
        return $html . '</ul>';
    }

    protected function renderAreaList(array $areas, string $current, string $workspace): string
    {
        if ($areas === []) return '<div class="tfv2-empty">Noch keine Bereiche.</div>';
        $html = '<ul class="tfv2-page-tree tfv2-area-tree">';
        foreach ($areas as $area) {
            $id = (string)($area['id'] ?? '');
            $active = $id === $current ? ' active' : '';
            $html .= '<li><a class="tfv2-page-link' . $active . '" href="/admin/explorer-v2/?area=' . rawurlencode($id) . '&workspace=' . rawurlencode($workspace) . '"><span>🧩 ' . $this->e((string)($area['title'] ?? $id)) . '</span><small>area:' . $this->e($id) . '</small></a></li>';
        }
        return $html . '</ul>';
    }

    protected function renderWorkspaces(string $current, array $stats, string $id, string $kind = 'page'): string
    {
        $items = ['published' => ['🌳','Published','live'], 'draft' => ['✏️','Draft','bearbeiten'], 'review' => ['🔎','Review','prüfen']];
        $html = '';
        foreach ($items as $key => [$icon, $label, $hint]) {
            $active = $key === $current ? ' active' : '';
            $count = (int)($stats[$key]['nodes'] ?? 0);
            $param = $kind === 'area' ? 'area=' : 'page=';
            $html .= '<a class="tfv2-workspace' . $active . '" href="/admin/explorer-v2/?' . $param . rawurlencode($id) . '&workspace=' . $key . '"><span class="tfv2-workspace-icon">' . $icon . '</span><span><strong>' . $label . '</strong><small>' . $count . ' Nodes · ' . $hint . '</small></span></a>';
        }
        return $html;
    }

    protected function renderArchives(array $archives): string
    {
        if ($archives === []) return '<div class="tfv2-empty">Noch keine Archive.</div>';
        $html = '<div class="tfv2-archives">';
        foreach (array_slice($archives, 0, 5) as $archive) $html .= '<a href="#">🕘 ' . $this->e((string)($archive['created_at'] ?? 'Version')) . '</a>';
        return $html . '</div>';
    }

    protected function renderNodeTree(array $node, int $level = 0): string
    {
        $id = (string)($node['id'] ?? 'node');
        $type = (string)($node['type'] ?? 'Node');
        $title = (string)($node['title'] ?? $type);
        $children = (array)($node['children'] ?? []);
        $hasChildren = $children !== [];
        $nodeJson = $this->e(json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');

        $toggle = $hasChildren
            ? '<button type="button" class="tfv2-node-toggle" data-node-toggle="' . $this->e($id) . '" aria-label="Node einklappen">▾</button>'
            : '<span class="tfv2-node-toggle placeholder">•</span>';

        $html = '<div class="tfv2-node-wrap" data-node-wrap="' . $this->e($id) . '" data-has-children="' . ($hasChildren ? '1' : '0') . '">'
            . '<div class="tfv2-node level-' . $level . '" data-node-id="' . $this->e($id) . '" data-node-json="' . $nodeJson . '">'
            . $toggle
            . '<span class="tfv2-node-icon">' . $this->nodeIcon($type) . '</span>'
            . '<span><strong>' . $this->e($title) . '</strong><small>' . $this->e($id) . ' · ' . $this->e($type) . '</small></span>'
            . '<span class="tfv2-badge">' . count($children) . '</span>'
            . $this->renderNodeToolbar($id, $hasChildren)
            . '</div>';

        if ($hasChildren) {
            $html .= '<div class="tfv2-node-children" data-node-children="' . $this->e($id) . '">';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= $this->renderNodeTree($child, $level + 1);
                }
            }
            $html .= '</div>';
        }

        return $html . '</div>';
    }

    protected function renderNodeToolbar(string $nodeId, bool $hasChildren): string
    {
        $id = $this->e($nodeId);
        return ''
            . '<div class="tfv2-node-toolbar" data-node-toolbar="' . $id . '">'
            . '<button type="button" title="Kind-Node hinzufügen" data-node-action="add-child" data-node-id="' . $id . '">＋</button>'
            . '<button type="button" title="Bearbeiten" data-node-action="edit" data-node-id="' . $id . '">✏</button>'
            . '<button type="button" title="Kopieren" data-node-action="copy" data-node-id="' . $id . '">📋</button>'
            . '<button type="button" title="Als Referenz einfügen" data-node-action="copy-reference" data-node-id="' . $id . '">🔗</button>'
            . '<button type="button" title="Duplizieren" data-node-action="duplicate" data-node-id="' . $id . '">🧬</button>'
            . '<button type="button" title="Verschieben" data-node-action="move" data-node-id="' . $id . '">↕</button>'
            . '<button type="button" title="Löschen" data-node-action="delete" data-node-id="' . $id . '">🗑</button>'
            . '</div>';
    }

    protected function nodeIcon(string $type): string
    {
        return match (strtolower($type)) {
            'rootnode' => '🌲',
            'heading', 'headingnode' => '🔠',
            'text', 'textnode' => '📝',
            'image', 'imagenode', 'bild' => '🖼️',
            'markdown', 'markdownnode' => '⬇️',
            'html', 'htmlnode' => '📄',
            'css', 'cssnode' => '🎨',
            'codeblock', 'codeblocknode' => '💻',
            'columns', 'columnsnode' => '▦',
            'column', 'columnnode' => '▥',
            'button', 'buttonnode' => '🔘',
            default => '📦',
        };
    }

    protected function countNodes(array $node): int
    {
        $count = 1;
        foreach ((array)($node['children'] ?? []) as $child) if (is_array($child)) $count += $this->countNodes($child);
        return $count;
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}