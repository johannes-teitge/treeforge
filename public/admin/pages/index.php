<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Pages\PageTreeManager;
use TreeForge\Core\Areas\AreaManager;
use TreeForge\Core\Settings\SettingsManager;

$root = dirname(__DIR__, 3);
$settings = new SettingsManager($root);
$data = $settings->all();

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function selected(string $current, string $value): string
{
    return $current === $value ? ' selected' : '';
}

function checked(bool $value): string
{
    return $value ? ' checked' : '';
}

$workspace = strtolower((string)($_GET['workspace'] ?? 'draft'));
$workspace = in_array($workspace, ['draft', 'review', 'published'], true) ? $workspace : 'draft';
$pages = new PageTreeManager($root, $workspace);
$areas = new AreaManager($root, $workspace);

$error = '';
$notice = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        $id = (string)($_POST['id'] ?? '');

        if ($action === 'create') {
            $created = $pages->create([
                'title' => (string)($_POST['title'] ?? ''),
                'slug' => (string)($_POST['slug'] ?? ''),
                'parent_id' => (string)($_POST['parent_id'] ?? ''),
                'language' => (string)($_POST['language'] ?? 'de'),
                'template' => (string)($_POST['template'] ?? 'default'),
            ]);
            $notice = 'Seite wurde im Draft angelegt: ' . (string)($created['id'] ?? '');
            $_GET['edit'] = (string)($created['id'] ?? '');
        }

        if ($action === 'update') {
            $updated = $pages->update($id, [
                'title' => (string)($_POST['title'] ?? ''),
                'parent_id' => (string)($_POST['parent_id'] ?? ''),
                'position' => (string)($_POST['position'] ?? '10'),
                'status' => (string)($_POST['status'] ?? 'draft'),
                'visibility' => (string)($_POST['visibility'] ?? 'visible'),
                'language' => (string)($_POST['language'] ?? 'de'),
                'template' => (string)($_POST['template'] ?? 'default'),
            ]);
            $notice = 'Seite wurde im Draft gespeichert: ' . (string)($updated['id'] ?? $id);
        }

        if ($action === 'review') {
            $pages->sendToReview($id);
            $notice = 'Draft wurde nach Review kopiert.';
        }

        if ($action === 'publish') {
            $pages->publish($id);
            $notice = 'Draft wurde veröffentlicht.';
        }

        if ($action === 'duplicate') {
            $copy = $pages->duplicateToDraft($id);
            $notice = 'Seite wurde als Draft dupliziert: ' . (string)($copy['id'] ?? '');
        }

        if ($action === 'delete_draft') {
            $pages->deleteDraft($id);
            $notice = 'Draft wurde in storage/trash/pages verschoben.';
        }

        if ($action === 'area_create') {
            $created = $areas->create([
                'title' => (string)($_POST['title'] ?? ''),
                'id' => (string)($_POST['area_id'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
            ]);
            $notice = 'Globaler Bereich wurde im Draft angelegt: ' . (string)($created['id'] ?? '');
        }

        if ($action === 'area_update') {
            $updated = $areas->update($id, [
                'title' => (string)($_POST['title'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
                'position' => (string)($_POST['position'] ?? '10'),
                'visibility' => (string)($_POST['visibility'] ?? 'hidden'),
            ]);
            $notice = 'Globaler Bereich wurde im Draft gespeichert: ' . (string)($updated['id'] ?? $id);
        }

        if ($action === 'area_review') {
            $areas->sendToReview($id);
            $notice = 'Bereich wurde nach Review kopiert.';
        }

        if ($action === 'area_publish') {
            $areas->publish($id);
            $notice = 'Bereich wurde veröffentlicht.';
        }

        if ($action === 'area_delete_draft') {
            $areas->deleteDraft($id);
            $notice = 'Bereich-Draft wurde in storage/trash/areas verschoben.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$allPages = $pages->all();
$allAreas = $areas->all();
$tree = $pages->tree();
$editId = (string)($_GET['edit'] ?? '');
$editPage = $editId !== '' ? $pages->find($editId) : null;
$editAreaId = (string)($_GET['edit_area'] ?? '');
$editArea = $editAreaId !== '' ? $areas->find($editAreaId) : null;

function workspaceTabs(string $current): string
{
    $items = [
        'draft' => '✏️ Draft',
        'review' => '🔎 Review',
        'published' => '🌳 Published',
    ];

    $html = '<nav class="tf-pages-tabs">';
    foreach ($items as $key => $label) {
        $html .= '<a class="' . ($current === $key ? 'active' : '') . '" href="/admin/pages/?workspace=' . e($key) . '">' . e($label) . '</a>';
    }
    return $html . '</nav>';
}

function workspaceBadges(array $page): string
{
    $presence = (array)($page['workspaces'] ?? []);
    $html = '<div class="tf-pages-workspace-badges">';
    foreach (['draft' => 'D', 'review' => 'R', 'published' => 'P'] as $key => $label) {
        $html .= '<span class="' . (!empty($presence[$key]) ? 'on' : 'off') . '" title="' . e($key) . '">' . e($label) . '</span>';
    }
    return $html . '</div>';
}

function pageOptions(array $allPages, string $selectedId = '', string $excludeId = ''): string
{
    $html = '<option value="">Root-Seite</option>';
    foreach ($allPages as $page) {
        $id = (string)($page['id'] ?? '');
        if ($id === '' || $id === $excludeId) {
            continue;
        }
        $html .= '<option value="' . e($id) . '"' . selected($selectedId, $id) . '>' . e((string)($page['title'] ?? $id)) . ' (' . e($id) . ')</option>';
    }
    return $html;
}

function renderTree(array $nodes, PageTreeManager $pages, string $workspace, int $level = 0): string
{
    if ($nodes === []) {
        return '<div class="tf-pages-empty">Noch keine Seiten vorhanden.</div>';
    }

    $html = '<ol class="tf-page-tree-list level-' . $level . '">';

    foreach ($nodes as $node) {
        $id = (string)($node['id'] ?? '');
        $title = (string)($node['title'] ?? $id);
        $status = (string)($node['status'] ?? 'draft');
        $path = $pages->pathFor($node);
        $nodeCount = (int)($node['node_count'] ?? 0);
        $sourceWorkspace = (string)($node['source_workspace'] ?? $workspace);

        $html .= '<li class="tf-page-tree-item">';
        $html .= '<div class="tf-page-tree-row tf-page-tree-row-v2">';
        $html .= '<div class="tf-page-tree-main">'
            . '<span class="tf-page-tree-icon">📄</span>'
            . '<div><strong>' . e($title) . '</strong>'
            . '<small><code>' . e($id) . '</code> · ' . e($path) . ' · Quelle: ' . e($sourceWorkspace) . '</small></div>'
            . '</div>';
        $html .= '<div class="tf-page-tree-meta">'
            . '<span class="tf-page-status ' . e($status) . '">' . e($status) . '</span>'
            . '<span>' . e((string)($node['language'] ?? 'de')) . '</span>'
            . '<span>' . e((string)($node['template'] ?? 'default')) . '</span>'
            . '<span>' . $nodeCount . ' Nodes</span>'
            . workspaceBadges($node)
            . '</div>';
        $html .= '<div class="tf-page-tree-actions">'
            . '<a class="tf-admin-button secondary small" href="/admin/pages/?workspace=' . e($workspace) . '&edit=' . e($id) . '">Meta</a>'
            . '<a class="tf-admin-button small" href="/admin/explorer-v2/?page=' . rawurlencode($id) . '&workspace=draft">V2 Draft</a>'
            . '<a class="tf-admin-button secondary small" target="_blank" href="/?page=' . rawurlencode($id) . '&workspace=draft">Preview</a>'
            . '<a class="tf-admin-button secondary small" href="/admin/page-settings/?page=' . rawurlencode($id) . '">Settings</a>'
            . '<form method="post" class="tf-inline-form"><input type="hidden" name="id" value="' . e($id) . '"><button class="tf-admin-button secondary small" name="action" value="review" type="submit">Review</button></form>'
            . '<form method="post" class="tf-inline-form"><input type="hidden" name="id" value="' . e($id) . '"><button class="tf-admin-button secondary small" name="action" value="publish" type="submit">Publish</button></form>'
            . '</div>';
        $html .= '</div>';

        if (!empty($node['children'])) {
            $html .= renderTree((array)$node['children'], $pages, $workspace, $level + 1);
        }

        $html .= '</li>';
    }

    return $html . '</ol>';
}
function renderAreasList(array $areas, string $workspace): string
{
    if ($areas === []) {
        return '<div class="tf-pages-empty">Noch keine globalen Bereiche vorhanden.</div>';
    }

    $html = '<ol class="tf-page-tree-list tf-area-tree-list">';

    foreach ($areas as $area) {
        $id = (string)($area['id'] ?? '');
        $title = (string)($area['title'] ?? $id);
        $status = (string)($area['status'] ?? 'draft');
        $nodeCount = (int)($area['node_count'] ?? 0);
        $sourceWorkspace = (string)($area['source_workspace'] ?? $workspace);
        $description = trim((string)($area['description'] ?? ''));

        $html .= '<li class="tf-page-tree-item tf-area-tree-item" id="area-' . e($id) . '">';
        $html .= '<div class="tf-page-tree-row tf-page-tree-row-v2">';
        $html .= '<div class="tf-page-tree-main">'
            . '<span class="tf-page-tree-icon">🧩</span>'
            . '<div><strong>' . e($title) . '</strong>'
            . '<small><code>' . e($id) . '</code> · area:' . e($id) . ' · Quelle: ' . e($sourceWorkspace) . '</small>'
            . ($description !== '' ? '<small>' . e($description) . '</small>' : '')
            . '</div></div>';

        $html .= '<div class="tf-page-tree-meta">'
            . '<span class="tf-page-status ' . e($status) . '">' . e($status) . '</span>'
            . '<span>hidden</span>'
            . '<span>' . $nodeCount . ' Nodes</span>'
            . workspaceBadges($area)
            . '</div>';

        $html .= '<div class="tf-page-tree-actions">'
            . '<a class="tf-admin-button secondary small" href="/admin/pages/?workspace=' . e($workspace) . '&edit_area=' . e($id) . '#global-areas">Meta</a>'
            . '<a class="tf-admin-button small" href="/admin/explorer-v2/?area=' . rawurlencode($id) . '&workspace=draft">V2 Draft</a>'
            . '<a class="tf-admin-button secondary small" href="/admin/explorer-v2/?area=' . rawurlencode($id) . '&workspace=published">Published</a>'
            . '<form method="post" class="tf-inline-form"><input type="hidden" name="id" value="' . e($id) . '"><button class="tf-admin-button secondary small" name="action" value="area_review" type="submit">Review</button></form>'
            . '<form method="post" class="tf-inline-form"><input type="hidden" name="id" value="' . e($id) . '"><button class="tf-admin-button secondary small" name="action" value="area_publish" type="submit">Publish</button></form>'
            . '</div>';

        $html .= '</div></li>';
    }

    return $html . '</ol>';
}

$parentOptions = pageOptions($allPages, (string)($_GET['parent'] ?? ''));

$content = '';

if ($notice !== '') {
    $content .= '<div class="tf-notice success">' . e($notice) . '</div>';
}
if ($error !== '') {
    $content .= '<div class="tf-notice error">' . e($error) . '</div>';
}

$content .= '<section class="tf-pages-shell tf-pages-shell-v2">';
$content .= '<section class="tf-pages-main tf-admin-card">'
    . '<div class="tf-pages-head"><div><h2>Workspace Pages</h2><p>Neue Seitenübersicht aus <code>storage/workspaces/*/pages</code>. <code>storage/pages</code> ist nur noch Legacy.</p></div><a class="tf-admin-button" href="#new-page">Neue Seite</a></div>'
    . workspaceTabs($workspace)
    . '<div class="tf-pages-legend"><span><b>D</b> Draft</span><span><b>R</b> Review</span><span><b>P</b> Published</span></div>';

$content .= renderTree($tree, $pages, $workspace);

$content .= '<section class="tf-pages-main tf-admin-card" id="global-areas"><div class="tf-pages-head"><div><h2>Globale Bereiche</h2><p>Wiederverwendbare Inhaltsbereiche aus <code>storage/workspaces/*/areas</code>. Sie haben keine eigene URL und werden später per Twig eingebunden.</p></div><a class="tf-admin-button" href="#new-area">Neuer Bereich</a></div>';
$content .= renderAreasList($allAreas, $workspace);
$content .= '</section>';

$content .= '</section><aside class="tf-pages-side">';

if ($editPage) {
    $content .= '<form method="post" class="tf-admin-card tf-page-form">'
        . '<input type="hidden" name="id" value="' . e($editPage['id'] ?? '') . '">'
        . '<h2>Seite bearbeiten</h2>'
        . '<p class="tf-page-form-hint">Metadaten werden im Draft gespeichert. Der Slug bleibt zur Sicherheit unverändert.</p>'
        . '<label><span>Titel</span><input type="text" name="title" value="' . e($editPage['title'] ?? '') . '"></label>'
        . '<label><span>Slug / Datei</span><input type="text" value="' . e($editPage['slug'] ?? $editPage['id'] ?? '') . '" disabled><small>Slug-Änderung später mit Redirect-Prüfung.</small></label>'
        . '<label><span>Parent</span><select name="parent_id">' . pageOptions($allPages, (string)($editPage['parent_id'] ?? ''), (string)($editPage['id'] ?? '')) . '</select></label>'
        . '<label><span>Position</span><input type="number" name="position" value="' . e($editPage['position'] ?? 10) . '"></label>'
        . '<label><span>Status</span><select name="status">'
        . '<option value="draft"' . selected((string)($editPage['status'] ?? 'draft'), 'draft') . '>Draft</option>'
        . '<option value="review"' . selected((string)($editPage['status'] ?? 'draft'), 'review') . '>Review</option>'
        . '<option value="published"' . selected((string)($editPage['status'] ?? 'draft'), 'published') . '>Published</option>'
        . '<option value="hidden"' . selected((string)($editPage['status'] ?? 'draft'), 'hidden') . '>Hidden</option>'
        . '</select></label>'
        . '<label><span>Sichtbarkeit</span><select name="visibility">'
        . '<option value="visible"' . selected((string)($editPage['visibility'] ?? 'visible'), 'visible') . '>Visible</option>'
        . '<option value="hidden"' . selected((string)($editPage['visibility'] ?? 'visible'), 'hidden') . '>Hidden</option>'
        . '</select></label>'
        . '<label><span>Sprache</span><input type="text" name="language" value="' . e($editPage['language'] ?? 'de') . '"></label>'
        . '<label><span>Template</span><input type="text" name="template" value="' . e($editPage['template'] ?? 'default') . '"></label>'
        . '<div class="tf-admin-actions tf-page-form-actions">'
        . '<button type="submit" name="action" value="update" class="tf-admin-button">Draft speichern</button>'
        . '<a class="tf-admin-button secondary" href="/admin/explorer-v2/?page=' . rawurlencode((string)($editPage['id'] ?? '')) . '&workspace=draft">Explorer V2</a>'
        . '</div>'
        . '<div class="tf-danger-zone">'
        . '<button type="submit" name="action" value="duplicate" class="tf-admin-button secondary">Duplizieren</button>'
        . '<button type="submit" name="action" value="delete_draft" class="tf-admin-button danger" onclick="return confirm(\'Draft wirklich in den Papierkorb verschieben? Published bleibt erhalten.\')">Draft löschen</button>'
        . '</div>'
        . '</form>';
}


if ($editArea) {
    $content .= '<form method="post" class="tf-admin-card tf-page-form">'
        . '<input type="hidden" name="id" value="' . e($editArea['id'] ?? '') . '">'
        . '<h2>Bereich bearbeiten</h2>'
        . '<p class="tf-page-form-hint">Globale Bereiche werden im Draft gespeichert und später per Twig eingebunden.</p>'
        . '<label><span>Titel</span><input type="text" name="title" value="' . e($editArea['title'] ?? '') . '"></label>'
        . '<label><span>ID</span><input type="text" value="' . e($editArea['id'] ?? '') . '" disabled></label>'
        . '<label><span>Beschreibung</span><textarea name="description" rows="3">' . e($editArea['description'] ?? '') . '</textarea></label>'
        . '<label><span>Position</span><input type="number" name="position" value="' . e($editArea['position'] ?? 10) . '"></label>'
        . '<label><span>Sichtbarkeit</span><select name="visibility">'
        . '<option value="hidden"' . selected((string)($editArea['visibility'] ?? 'hidden'), 'hidden') . '>Hidden</option>'
        . '<option value="visible"' . selected((string)($editArea['visibility'] ?? 'hidden'), 'visible') . '>Visible</option>'
        . '</select></label>'
        . '<div class="tf-admin-actions tf-page-form-actions">'
        . '<button type="submit" name="action" value="area_update" class="tf-admin-button">Draft speichern</button>'
        . '<a class="tf-admin-button secondary" href="/admin/explorer-v2/?area=' . rawurlencode((string)($editArea['id'] ?? '')) . '&workspace=draft">Explorer V2</a>'
        . '</div>'
        . '<div class="tf-danger-zone">'
        . '<button type="submit" name="action" value="area_delete_draft" class="tf-admin-button danger" onclick="return confirm(\'Bereich-Draft wirklich in den Papierkorb verschieben? Published bleibt erhalten.\')">Draft löschen</button>'
        . '</div>'
        . '</form>';
}
$content .= '<form method="post" id="new-page" class="tf-admin-card tf-page-form">'
    . '<h2>Neue Seite</h2>'
    . '<p class="tf-page-form-hint">Neue Seiten werden immer zuerst im Draft-Workspace angelegt.</p>'
    . '<label><span>Titel</span><input type="text" name="title" placeholder="z. B. Über uns"></label>'
    . '<label><span>Slug</span><input type="text" name="slug" placeholder="optional, automatisch aus Titel"></label>'
    . '<label><span>Parent</span><select name="parent_id">' . $parentOptions . '</select></label>'
    . '<label><span>Sprache</span><input type="text" name="language" value="' . e($data['languages']['default_language'] ?? 'de') . '"></label>'
    . '<label><span>Template</span><input type="text" name="template" value="default"></label>'
    . '<button type="submit" name="action" value="create" class="tf-admin-button">Seite anlegen</button>'
    . '</form>';


$content .= '<form method="post" id="new-area" class="tf-admin-card tf-page-form">'
    . '<h2>Neuer Bereich</h2>'
    . '<p class="tf-page-form-hint">Globale Bereiche werden zuerst im Draft-Workspace angelegt, z. B. footer, header oder sidebar.</p>'
    . '<label><span>Titel</span><input type="text" name="title" placeholder="z. B. Footer"></label>'
    . '<label><span>ID</span><input type="text" name="area_id" placeholder="optional, z. B. footer"></label>'
    . '<label><span>Beschreibung</span><textarea name="description" rows="3" placeholder="Wofür wird dieser Bereich verwendet?"></textarea></label>'
    . '<button type="submit" name="action" value="area_create" class="tf-admin-button">Bereich anlegen</button>'
    . '</form>';
$content .= '<section class="tf-admin-card tf-page-form">'
    . '<h2>Aufräumen</h2>'
    . '<p class="tf-page-form-hint">Wenn diese neue Übersicht sauber läuft, kannst du die alte <code>storage/pages</code>-Struktur archivieren.</p>'
    . '<code>php tools/archive-legacy-pages.php --dry-run</code><br>'
    . '<code>php tools/archive-legacy-pages.php</code>'
    . '</section>';

$content .= '</aside></section>';

$content .= '<section class="tf-admin-card tf-pages-legacy-note">'
    . '<h2>Legacy-Hinweis</h2>'
    . '<p><code>storage/pages</code> wird von dieser Übersicht nicht mehr als Hauptquelle verwendet. Die Hauptquelle sind jetzt die Workspace-Dateien. Alte Metadaten werden nur noch als Fallback gelesen, solange der Legacy-Ordner existiert.</p>'
    . '</section>';

echo (new AdminLayout())->render(
    'Pages',
    $content,
    'pages',
    [
        'site_name' => (string)($data['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Workspace Page Manager',
    ]
);