<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 038
 * Backend Shell
 *
 * Ziel:
 * - gemeinsame Backend-Shell für Admin-Seiten
 * - AdminLayout und AdminMenu
 * - Dashboard unter /admin/
 * - Settings-Seite nutzt erstes gemeinsames Backend-Layout
 *
 * Dateien:
 * - app/Admin/AdminMenu.php
 * - app/Admin/AdminLayout.php
 * - public/admin/index.php
 * - public/assets/css/admin.css
 * - public/admin/settings/index.php
 * - docs/treeforge/28-backend-shell.md
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

    $log('Patch 038 Backend Shell gestartet');

    $write($root . '/app/Admin/AdminMenu.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Admin;

class AdminMenu
{
    public static function items(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'href' => '/admin/',
                'icon' => '⌂',
                'key' => 'dashboard',
            ],
            [
                'label' => 'Explorer',
                'href' => '/explorer',
                'icon' => '🌳',
                'key' => 'explorer',
            ],
            [
                'label' => 'Archive',
                'href' => '/archives',
                'icon' => '📦',
                'key' => 'archives',
            ],
            [
                'label' => 'Media',
                'href' => '#',
                'icon' => '🖼',
                'key' => 'media',
                'disabled' => true,
            ],
            [
                'label' => 'Templates',
                'href' => '#',
                'icon' => '🎨',
                'key' => 'templates',
                'disabled' => true,
            ],
            [
                'label' => 'Nodes',
                'href' => '#',
                'icon' => '🧩',
                'key' => 'nodes',
                'disabled' => true,
            ],
            [
                'label' => 'Docs',
                'href' => '/docs-viewer/',
                'icon' => '📚',
                'key' => 'docs',
            ],
            [
                'label' => 'Settings',
                'href' => '/admin/settings/',
                'icon' => '⚙',
                'key' => 'settings',
            ],
        ];
    }
}
PHP);

    $write($root . '/app/Admin/AdminLayout.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Admin;

class AdminLayout
{
    public function render(string $title, string $content, string $active = 'dashboard', array $options = []): string
    {
        $siteName = (string)($options['site_name'] ?? 'TreeForge CMS');
        $subtitle = (string)($options['subtitle'] ?? 'Backend');

        return '<!doctype html>'
            . '<html lang="de">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>' . $this->e($title) . ' · TreeForge</title>'
            . '<link rel="stylesheet" href="/assets/css/admin.css">'
            . '</head>'
            . '<body>'
            . '<div class="tf-admin-shell">'
            . $this->sidebar($active, $siteName)
            . '<div class="tf-admin-main">'
            . '<header class="tf-admin-topbar">'
            . '<div>'
            . '<h1>' . $this->e($title) . '</h1>'
            . '<p>' . $this->e($subtitle) . '</p>'
            . '</div>'
            . '<nav class="tf-admin-quicklinks">'
            . '<a href="/" target="_blank" rel="noopener">Frontend</a>'
            . '<a href="https://github.com/johannes-teitge/treeforge" target="_blank" rel="noopener">GitHub</a>'
            . '</nav>'
            . '</header>'
            . '<main class="tf-admin-content">'
            . $content
            . '</main>'
            . '</div>'
            . '</div>'
            . '</body>'
            . '</html>';
    }

    protected function sidebar(string $active, string $siteName): string
    {
        $html = '<aside class="tf-admin-sidebar">';
        $html .= '<a class="tf-admin-brand" href="/admin/">TREE<span>FORGE</span></a>';
        $html .= '<div class="tf-admin-site">' . $this->e($siteName) . '</div>';
        $html .= '<nav class="tf-admin-menu">';

        foreach (AdminMenu::items() as $item) {
            $isActive = ($item['key'] ?? '') === $active;
            $disabled = !empty($item['disabled']);

            $class = 'tf-admin-menu-link'
                . ($isActive ? ' active' : '')
                . ($disabled ? ' disabled' : '');

            $href = $disabled ? '#' : (string)$item['href'];

            $html .= '<a class="' . $class . '" href="' . $this->e($href) . '">'
                . '<span class="tf-admin-menu-icon">' . $this->e((string)$item['icon']) . '</span>'
                . '<span>' . $this->e((string)$item['label']) . '</span>'
                . ($disabled ? '<small>geplant</small>' : '')
                . '</a>';
        }

        $html .= '</nav>';
        $html .= '</aside>';

        return $html;
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
PHP);

    $write($root . '/public/assets/css/admin.css', <<<'CSS'
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

html {
  scroll-behavior: smooth;
}

body {
  margin: 0;
  background: var(--tf-light);
  color: var(--tf-dark);
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.tf-admin-shell {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
}

.tf-admin-sidebar {
  background: #0d1411;
  color: #d7e2dc;
  padding: 1rem;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow: auto;
}

.tf-admin-brand {
  display: block;
  color: #fff;
  text-decoration: none;
  font-weight: 950;
  letter-spacing: .08em;
  font-size: 1.25rem;
  padding: .7rem .75rem .2rem;
}

.tf-admin-brand span {
  color: var(--tf-gold);
}

.tf-admin-site {
  color: #a8b8b1;
  font-size: .9rem;
  padding: 0 .75rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, .08);
  margin-bottom: .8rem;
}

.tf-admin-menu {
  display: grid;
  gap: .25rem;
}

.tf-admin-menu-link {
  display: grid;
  grid-template-columns: 2rem minmax(0, 1fr) auto;
  align-items: center;
  gap: .5rem;
  color: #d7e2dc;
  text-decoration: none;
  padding: .72rem .75rem;
  border-radius: .85rem;
  font-weight: 800;
}

.tf-admin-menu-link:hover,
.tf-admin-menu-link.active {
  background: rgba(216, 138, 34, .16);
  color: #fff;
}

.tf-admin-menu-link.disabled {
  opacity: .55;
  cursor: not-allowed;
}

.tf-admin-menu-link small {
  color: #d8aa73;
  font-size: .72rem;
}

.tf-admin-menu-icon {
  text-align: center;
}

.tf-admin-main {
  min-width: 0;
}

.tf-admin-topbar {
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

.tf-admin-topbar h1 {
  margin: 0;
  color: var(--tf-green);
  font-size: 1.5rem;
}

.tf-admin-topbar p {
  margin: .2rem 0 0;
  color: var(--tf-muted);
}

.tf-admin-quicklinks {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}

.tf-admin-quicklinks a {
  display: inline-flex;
  padding: .55rem .75rem;
  border-radius: .75rem;
  background: #fff;
  border: 1px solid var(--tf-border);
  color: var(--tf-green);
  text-decoration: none;
  font-weight: 800;
}

.tf-admin-content {
  padding: 1rem;
}

.tf-admin-card {
  background: var(--tf-cream);
  border: 1px solid var(--tf-border);
  border-radius: 1.1rem;
  padding: clamp(1rem, 3vw, 2rem);
  box-shadow: 0 1rem 2.8rem rgba(18, 26, 23, .05);
}

.tf-admin-card h2 {
  margin: 0 0 .5rem;
  color: var(--tf-green);
}

.tf-admin-card p {
  color: var(--tf-muted);
}

.tf-admin-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.tf-admin-stat {
  background: #fff;
  border: 1px solid var(--tf-border);
  border-radius: 1rem;
  padding: 1rem;
}

.tf-admin-stat strong {
  display: block;
  color: var(--tf-green);
  font-size: 1.6rem;
}

.tf-admin-stat span {
  color: var(--tf-muted);
  font-weight: 800;
}

.tf-admin-actions {
  display: flex;
  flex-wrap: wrap;
  gap: .6rem;
  margin-top: 1rem;
}

.tf-admin-button {
  display: inline-flex;
  padding: .7rem .9rem;
  border-radius: .8rem;
  background: var(--tf-green);
  color: #fff;
  text-decoration: none;
  font-weight: 900;
}

.tf-admin-button.secondary {
  background: #fff;
  color: var(--tf-green);
  border: 1px solid var(--tf-border);
}

.tf-settings-form {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr);
  gap: 1rem;
}

.tf-settings-tabs {
  background: var(--tf-cream);
  border: 1px solid var(--tf-border);
  border-radius: 1.1rem;
  padding: .75rem;
  height: max-content;
  position: sticky;
  top: 108px;
}

.tf-settings-tabs a {
  display: block;
  padding: .75rem .85rem;
  color: var(--tf-dark);
  text-decoration: none;
  border-radius: .8rem;
  font-weight: 800;
  margin-bottom: .25rem;
}

.tf-settings-tabs a:hover {
  background: rgba(216, 138, 34, .14);
  color: var(--tf-green);
}

.tf-settings-content {
  display: grid;
  gap: 1rem;
}

.tf-settings-card {
  background: var(--tf-cream);
  border: 1px solid var(--tf-border);
  border-radius: 1.1rem;
  padding: clamp(1rem, 3vw, 2rem);
  box-shadow: 0 1rem 2.8rem rgba(18, 26, 23, .05);
}

.tf-settings-card h1,
.tf-settings-card h2 {
  margin: 0 0 .35rem;
  color: var(--tf-green);
}

.tf-settings-card p {
  margin: 0 0 1.25rem;
  color: var(--tf-muted);
}

.tf-settings-card label {
  display: grid;
  gap: .35rem;
  margin-bottom: 1rem;
  font-weight: 850;
  color: var(--tf-green);
}

.tf-settings-card input,
.tf-settings-card select {
  width: 100%;
  border: 1px solid rgba(23, 63, 53, .22);
  border-radius: .8rem;
  padding: .75rem .85rem;
  font: inherit;
  background: #fff;
  color: var(--tf-dark);
}

.tf-settings-card small {
  color: var(--tf-muted);
  font-weight: 650;
}

.tf-check {
  display: flex !important;
  grid-template-columns: none !important;
  align-items: center;
  gap: .7rem !important;
}

.tf-check input {
  width: auto;
  transform: scale(1.2);
}

.tf-warning {
  padding: .9rem 1rem;
  border-radius: .9rem;
  background: rgba(216, 138, 34, .14);
  border: 1px solid rgba(216, 138, 34, .22);
  color: #76470d;
  font-weight: 800;
}

.tf-system-info {
  display: grid;
  grid-template-columns: 190px minmax(0, 1fr);
  gap: .65rem 1rem;
  margin: 0;
}

.tf-system-info dt {
  color: var(--tf-green);
  font-weight: 900;
}

.tf-system-info dd {
  margin: 0;
  color: #2f3b36;
  overflow-wrap: anywhere;
}

.tf-system-info code {
  background: rgba(23, 63, 53, .08);
  padding: .15rem .35rem;
  border-radius: .35rem;
}

.tf-settings-savebar {
  position: sticky;
  bottom: 0;
  background: rgba(245, 243, 234, .92);
  border: 1px solid var(--tf-border);
  border-radius: 1rem;
  padding: .8rem;
  display: flex;
  justify-content: flex-end;
  backdrop-filter: blur(12px);
}

.tf-settings-savebar button {
  border: 0;
  background: var(--tf-green);
  color: #fff;
  padding: .8rem 1rem;
  border-radius: .8rem;
  font: inherit;
  font-weight: 900;
  cursor: pointer;
}

.tf-settings-savebar button:hover {
  background: #0f2d26;
}

.tf-notice {
  margin: 0 0 1rem;
  padding: .85rem 1rem;
  border-radius: .85rem;
  font-weight: 800;
}

.tf-notice.success {
  background: rgba(30, 120, 60, .14);
  color: #17572c;
  border: 1px solid rgba(30, 120, 60, .22);
}

.tf-notice.error {
  background: rgba(138, 59, 20, .12);
  color: #8a3b14;
  border: 1px solid rgba(138, 59, 20, .22);
}

@media (max-width: 1100px) {
  .tf-admin-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 900px) {
  .tf-admin-shell {
    grid-template-columns: 1fr;
  }

  .tf-admin-sidebar {
    position: static;
    height: auto;
  }

  .tf-admin-topbar {
    position: static;
    flex-direction: column;
    align-items: flex-start;
  }

  .tf-settings-form {
    grid-template-columns: 1fr;
  }

  .tf-settings-tabs {
    position: static;
  }

  .tf-system-info {
    grid-template-columns: 1fr;
  }
}
CSS);

    $write($root . '/public/admin/index.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Settings\SettingsManager;

$root = dirname(__DIR__, 2);
$settings = new SettingsManager($root);
$data = $settings->all();

function countFiles(string $pattern): int
{
    return count(glob($pattern) ?: []);
}

$pageCount = countFiles($root . '/storage/workspaces/published/pages/*.json');
$archiveCount = countFiles($root . '/storage/workspaces/archive/pages/home/*.json');
$docCount = countFiles($root . '/docs/treeforge/*.md');

$gitTag = trim((string)@shell_exec('git -C ' . escapeshellarg($root) . ' describe --tags --abbrev=0 2>NUL'));
$gitCommit = trim((string)@shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD 2>NUL'));

$content = ''
    . '<section class="tf-admin-card">'
    . '<h2>Willkommen bei TreeForge</h2>'
    . '<p>Dies ist die erste gemeinsame Backend-Shell. Weitere Bereiche werden schrittweise angebunden.</p>'
    . '<div class="tf-admin-grid">'
    . '<div class="tf-admin-stat"><strong>' . $pageCount . '</strong><span>Published Pages</span></div>'
    . '<div class="tf-admin-stat"><strong>' . $archiveCount . '</strong><span>Archive</span></div>'
    . '<div class="tf-admin-stat"><strong>' . $docCount . '</strong><span>Docs</span></div>'
    . '</div>'
    . '<div class="tf-admin-actions">'
    . '<a class="tf-admin-button" href="/explorer">Explorer öffnen</a>'
    . '<a class="tf-admin-button secondary" href="/archives">Archive Center</a>'
    . '<a class="tf-admin-button secondary" href="/admin/settings/">Settings</a>'
    . '<a class="tf-admin-button secondary" href="/docs-viewer/">Docs Viewer</a>'
    . '</div>'
    . '</section>'
    . '<section class="tf-admin-card" style="margin-top:1rem">'
    . '<h2>System</h2>'
    . '<dl class="tf-system-info">'
    . '<dt>Site Name</dt><dd>' . htmlspecialchars((string)($data['general']['site_name'] ?? 'TreeForge CMS'), ENT_QUOTES, 'UTF-8') . '</dd>'
    . '<dt>Storage</dt><dd>' . htmlspecialchars((string)($data['storage']['driver'] ?? 'file'), ENT_QUOTES, 'UTF-8') . '</dd>'
    . '<dt>Default Language</dt><dd>' . htmlspecialchars((string)($data['languages']['default_language'] ?? 'de'), ENT_QUOTES, 'UTF-8') . '</dd>'
    . '<dt>PHP</dt><dd>' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</dd>'
    . '<dt>Git Tag</dt><dd>' . htmlspecialchars($gitTag ?: 'unknown', ENT_QUOTES, 'UTF-8') . '</dd>'
    . '<dt>Git Commit</dt><dd>' . htmlspecialchars($gitCommit ?: 'unknown', ENT_QUOTES, 'UTF-8') . '</dd>'
    . '</dl>'
    . '</section>';

echo (new AdminLayout())->render(
    'Dashboard',
    $content,
    'dashboard',
    [
        'site_name' => (string)($data['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Systemübersicht',
    ]
);
PHP);

    $write($root . '/public/admin/settings/index.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Settings\SettingsManager;

$root = dirname(__DIR__, 3);
$settings = new SettingsManager($root);
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $enabledLanguages = array_values(array_filter(array_map(
            static fn(string $value): string => trim($value),
            explode(',', (string)($_POST['languages']['enabled_languages'] ?? 'de'))
        )));

        if ($enabledLanguages === []) {
            $enabledLanguages = ['de'];
        }

        $defaultLanguage = trim((string)($_POST['languages']['default_language'] ?? $enabledLanguages[0]));

        if ($defaultLanguage === '') {
            $defaultLanguage = $enabledLanguages[0];
        }

        if (!in_array($defaultLanguage, $enabledLanguages, true)) {
            array_unshift($enabledLanguages, $defaultLanguage);
            $enabledLanguages = array_values(array_unique($enabledLanguages));
        }

        $settings->merge([
            'general' => [
                'site_name' => trim((string)($_POST['general']['site_name'] ?? 'TreeForge CMS')),
                'site_url' => trim((string)($_POST['general']['site_url'] ?? 'http://localhost')),
                'admin_email' => trim((string)($_POST['general']['admin_email'] ?? '')),
                'timezone' => trim((string)($_POST['general']['timezone'] ?? 'Europe/Berlin')),
            ],
            'languages' => [
                'default_language' => $defaultLanguage,
                'enabled_languages' => $enabledLanguages,
                'multilanguage' => isset($_POST['languages']['multilanguage']),
            ],
            'storage' => [
                'driver' => (string)($_POST['storage']['driver'] ?? 'file'),
                'database_path' => trim((string)($_POST['storage']['database_path'] ?? 'storage/database/treeforge.sqlite')),
            ],
        ]);

        $settings->save();
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$data = $settings->all();

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function checked(bool $value): string
{
    return $value ? ' checked' : '';
}

function selected(string $current, string $value): string
{
    return $current === $value ? ' selected' : '';
}

$version = 'unknown';
$composerFile = $root . '/composer.json';

if (file_exists($composerFile)) {
    $composer = json_decode((string)file_get_contents($composerFile), true);

    if (is_array($composer) && isset($composer['version'])) {
        $version = (string)$composer['version'];
    }
}

$gitTag = trim((string)@shell_exec('git -C ' . escapeshellarg($root) . ' describe --tags --abbrev=0 2>NUL'));
$gitCommit = trim((string)@shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD 2>NUL'));

$content = '';

if ($saved) {
    $content .= '<div class="tf-notice success">Einstellungen wurden gespeichert.</div>';
}

if ($error !== '') {
    $content .= '<div class="tf-notice error">' . e($error) . '</div>';
}

$content .= '<form method="post" class="tf-settings-form">'
    . '<aside class="tf-settings-tabs" aria-label="Settings Navigation">'
    . '<a href="#general">General</a>'
    . '<a href="#languages">Languages</a>'
    . '<a href="#storage">Storage</a>'
    . '<a href="#system">System Info</a>'
    . '</aside>'
    . '<section class="tf-settings-content">'
    . '<section id="general" class="tf-settings-card">'
    . '<h1>General</h1>'
    . '<p>Grunddaten der Website und Umgebung.</p>'
    . '<label><span>Site Name</span><input type="text" name="general[site_name]" value="' . e($data['general']['site_name'] ?? '') . '"></label>'
    . '<label><span>Site URL</span><input type="url" name="general[site_url]" value="' . e($data['general']['site_url'] ?? '') . '"></label>'
    . '<label><span>Admin E-Mail</span><input type="email" name="general[admin_email]" value="' . e($data['general']['admin_email'] ?? '') . '"></label>'
    . '<label><span>Timezone</span><input type="text" name="general[timezone]" value="' . e($data['general']['timezone'] ?? 'Europe/Berlin') . '"></label>'
    . '</section>'
    . '<section id="languages" class="tf-settings-card">'
    . '<h2>Languages</h2>'
    . '<p>Auch ohne Multilanguage wird intern immer eine Default-Sprache gesetzt.</p>'
    . '<label><span>Default Language</span><input type="text" name="languages[default_language]" value="' . e($data['languages']['default_language'] ?? 'de') . '"></label>'
    . '<label><span>Enabled Languages</span><input type="text" name="languages[enabled_languages]" value="' . e(implode(',', (array)($data['languages']['enabled_languages'] ?? ['de']))) . '"><small>Kommagetrennt, z. B. de,en,fr</small></label>'
    . '<label class="tf-check"><input type="checkbox" name="languages[multilanguage]" value="1"' . checked((bool)($data['languages']['multilanguage'] ?? false)) . '><span>Multilanguage aktivieren</span></label>'
    . '</section>'
    . '<section id="storage" class="tf-settings-card">'
    . '<h2>Storage</h2>'
    . '<p>Aktuell arbeitet TreeForge mit FileStorage. SQLite/MySQL sind vorbereitet.</p>'
    . '<label><span>Storage Driver</span><select name="storage[driver]">'
    . '<option value="file"' . selected((string)($data['storage']['driver'] ?? 'file'), 'file') . '>File</option>'
    . '<option value="sqlite"' . selected((string)($data['storage']['driver'] ?? 'file'), 'sqlite') . '>SQLite</option>'
    . '<option value="mysql"' . selected((string)($data['storage']['driver'] ?? 'file'), 'mysql') . '>MySQL</option>'
    . '</select></label>'
    . '<label><span>SQLite Database Path</span><input type="text" name="storage[database_path]" value="' . e($data['storage']['database_path'] ?? 'storage/database/treeforge.sqlite') . '"></label>'
    . '<div class="tf-warning">Der Storage-Treiber wird aktuell nur gespeichert. Eine aktive Umschaltung erfolgt erst mit dem späteren StorageInterface-Patch.</div>'
    . '</section>'
    . '<section id="system" class="tf-settings-card">'
    . '<h2>System Info</h2>'
    . '<p>Erste technische Übersicht für spätere Support- und Diagnosefunktionen.</p>'
    . '<dl class="tf-system-info">'
    . '<dt>TreeForge Version</dt><dd>' . e($version) . '</dd>'
    . '<dt>PHP Version</dt><dd>' . e(PHP_VERSION) . '</dd>'
    . '<dt>Storage Driver</dt><dd>' . e($data['storage']['driver'] ?? 'file') . '</dd>'
    . '<dt>Default Language</dt><dd>' . e($data['languages']['default_language'] ?? 'de') . '</dd>'
    . '<dt>Git Tag</dt><dd>' . e($gitTag ?: 'unknown') . '</dd>'
    . '<dt>Git Commit</dt><dd>' . e($gitCommit ?: 'unknown') . '</dd>'
    . '<dt>Settings File</dt><dd><code>storage/system/settings.json</code></dd>'
    . '</dl>'
    . '</section>'
    . '<div class="tf-settings-savebar"><button type="submit">Einstellungen speichern</button></div>'
    . '</section>'
    . '</form>';

echo (new AdminLayout())->render(
    'Settings',
    $content,
    'settings',
    [
        'site_name' => (string)($data['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'Zentrale Systemeinstellungen',
    ]
);
PHP);

    $write($root . '/docs/treeforge/28-backend-shell.md', <<<'MD'
# Backend Shell

Patch 038 ergänzt eine erste gemeinsame Backend-Shell.

## Dateien

```text
app/Admin/AdminMenu.php
app/Admin/AdminLayout.php
public/admin/index.php
public/assets/css/admin.css
public/admin/settings/index.php
```

## Routen

```text
/admin/
/admin/settings/
```

## Ziel

Admin-Seiten sollen nicht mehr jeweils eigene Header und Layouts mitbringen.

Stattdessen gibt es eine gemeinsame Shell mit:

- Sidebar
- Hauptnavigation
- Topbar
- Content-Bereich
- Quicklinks

## Vorbereitete Bereiche

```text
Dashboard
Explorer
Archive
Media
Templates
Nodes
Docs
Settings
```

Einige Bereiche sind noch als "geplant" markiert.
MD);

    $log('Patch 038 Backend Shell fertig');
};
