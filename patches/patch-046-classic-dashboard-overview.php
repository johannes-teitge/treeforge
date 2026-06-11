<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 046
 * Classic Dashboard Overview
 *
 * Ziel:
 * - Dashboard im TreeForge Classic Stil aufwerten
 * - Quick Tiles für Pages, Media, Archives, Settings, Docs, Templates, Users, Security
 * - Platzhalter für Webstatistik
 * - Platzhalter für Security Overview
 * - Geo-Blocking Konzept in Settings vorbereiten
 *
 * Dateien:
 * - public/admin/index.php
 * - public/assets/css/dashboard.css
 * - app/Admin/AdminLayout.php
 * - app/Core/Settings/SettingsManager.php
 * - storage/system/settings.json
 * - docs/treeforge/36-classic-dashboard-overview.md
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

    $log('Patch 046 Classic Dashboard Overview gestartet');

    $settingsManagerFile = $root . '/app/Core/Settings/SettingsManager.php';

    if (file_exists($settingsManagerFile)) {
        $settingsManager = file_get_contents($settingsManagerFile);

        if (!str_contains($settingsManager, "'geo_blocking' =>")) {
            $old = <<<'PHP'
                'retention_days' => 30,
            ],
PHP;

            $new = <<<'PHP'
                'retention_days' => 30,
                'geo_blocking' => [
                    'enabled' => false,
                    'mode' => 'log',
                    'allow_countries' => ['DE', 'AT', 'CH'],
                    'block_countries' => [],
                    'log_only_countries' => ['CN', 'RU', 'BY', 'KP', 'IR'],
                    'unknown_country_action' => 'log',
                ],
            ],
PHP;

            $settingsManager = preg_replace('/\'security\' => \[(.*?)\'retention_days\' => 30,\s+\]/s', "'security' => [$1" . trim($new), $settingsManager, 1);

            if (!str_contains($settingsManager, "'geo_blocking' =>")) {
                $settingsManager = str_replace($old, $new, $settingsManager);
            }

            $write($settingsManagerFile, $settingsManager);
        }
    }

    $settingsJsonFile = $root . '/storage/system/settings.json';

    if (file_exists($settingsJsonFile)) {
        $settings = json_decode((string)file_get_contents($settingsJsonFile), true);

        if (is_array($settings)) {
            $settings['security'] ??= [];
            $settings['security']['geo_blocking'] ??= [
                'enabled' => false,
                'mode' => 'log',
                'allow_countries' => ['DE', 'AT', 'CH'],
                'block_countries' => [],
                'log_only_countries' => ['CN', 'RU', 'BY', 'KP', 'IR'],
                'unknown_country_action' => 'log',
            ];

            $write(
                $settingsJsonFile,
                json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }
    }

    $write($root . '/public/assets/css/dashboard.css', <<<'CSS'
.tf-dashboard-hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 330px;
  gap: 1rem;
  margin-bottom: 1rem;
}

.tf-dashboard-welcome,
.tf-dashboard-status,
.tf-dashboard-section,
.tf-dashboard-tile {
  background: var(--tf-bg-card, #FFFFFF);
  border: 1px solid var(--tf-border-default, #D7DDE2);
  border-radius: var(--tf-radius-md, .75rem);
  box-shadow: var(--tf-shadow-xs, 0 1px 2px rgba(18,26,23,.04));
}

.tf-dashboard-welcome {
  padding: 1.15rem;
}

.tf-dashboard-welcome h2 {
  margin: 0 0 .35rem;
  font-size: 1.45rem;
  font-weight: 650;
  letter-spacing: -.025em;
  color: var(--tf-text-heading, #071725);
}

.tf-dashboard-welcome p {
  margin: 0;
  color: var(--tf-text-muted, #64727D);
  line-height: 1.55;
}

.tf-dashboard-status {
  padding: 1rem;
}

.tf-dashboard-status h3,
.tf-dashboard-section h3 {
  margin: 0 0 .75rem;
  font-size: 1rem;
  font-weight: 620;
  color: var(--tf-text-heading, #071725);
}

.tf-status-list {
  display: grid;
  gap: .45rem;
}

.tf-status-row {
  display: flex;
  justify-content: space-between;
  gap: .75rem;
  color: var(--tf-text-muted, #64727D);
  font-size: .92rem;
}

.tf-status-row strong {
  color: var(--tf-text-default, #071725);
  font-weight: 560;
}

.tf-dot {
  width: .62rem;
  height: .62rem;
  display: inline-block;
  border-radius: var(--tf-radius-pill, 999px);
  margin-right: .35rem;
  background: var(--tf-state-success-text, #15713A);
}

.tf-dot.warning {
  background: var(--tf-state-warning-text, #8A6400);
}

.tf-dot.danger {
  background: var(--tf-state-danger-text, #C62828);
}

.tf-dashboard-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.tf-dashboard-tile {
  position: relative;
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr);
  gap: .85rem;
  padding: 1rem;
  text-decoration: none;
  color: var(--tf-text-default, #071725);
  min-height: 132px;
  overflow: hidden;
}

.tf-dashboard-tile::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(145deg, rgba(255,255,255,.88), rgba(234,241,245,.45));
  pointer-events: none;
}

.tf-dashboard-tile:hover {
  border-color: var(--tf-border-strong, #B7C1C8);
  transform: translateY(-1px);
}

.tf-dashboard-tile > * {
  position: relative;
}

.tf-dashboard-icon {
  width: 54px;
  height: 54px;
  border-radius: 16px;
  background:
    linear-gradient(145deg, #FFFFFF, #D9E0E5);
  border: 1px solid var(--tf-border-default, #D7DDE2);
  box-shadow:
    inset 0 1px 0 rgba(255,255,255,.9),
    0 .4rem 1rem rgba(7,23,37,.08);
  color: var(--tf-text-muted, #64727D);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.tf-dashboard-icon svg {
  width: 28px;
  height: 28px;
  stroke-width: 1.7;
}

.tf-dashboard-tile:hover .tf-dashboard-icon {
  color: var(--tf-color-primary, #173F35);
}

.tf-dashboard-tile h3 {
  margin: .05rem 0 .25rem;
  font-size: 1.05rem;
  font-weight: 620;
  color: var(--tf-text-heading, #071725);
}

.tf-dashboard-tile p {
  margin: 0;
  color: var(--tf-text-muted, #64727D);
  font-size: .9rem;
  line-height: 1.4;
}

.tf-dashboard-tile small {
  display: block;
  margin-top: .55rem;
  color: var(--tf-color-secondary-dark, #9B5E13);
  font-weight: 600;
}

.tf-dashboard-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.tf-dashboard-section {
  padding: 1rem;
}

.tf-dashboard-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: .75rem;
  margin-bottom: 1rem;
}

.tf-metric {
  background: var(--tf-bg-panel, #FFFFFF);
  border: 1px solid var(--tf-border-soft, #E5E9EC);
  border-radius: var(--tf-radius-sm, .5rem);
  padding: .75rem;
}

.tf-metric strong {
  display: block;
  font-size: 1.25rem;
  line-height: 1;
  font-weight: 650;
  color: var(--tf-text-heading, #071725);
}

.tf-metric span {
  display: block;
  margin-top: .25rem;
  color: var(--tf-text-muted, #64727D);
  font-size: .86rem;
}

.tf-dashboard-table {
  width: 100%;
  border-collapse: collapse;
}

.tf-dashboard-table th,
.tf-dashboard-table td {
  padding: .52rem .4rem;
  border-bottom: 1px solid var(--tf-border-soft, #E5E9EC);
  text-align: left;
  font-size: .9rem;
}

.tf-dashboard-table th {
  color: var(--tf-text-default, #071725);
  font-weight: 620;
}

.tf-dashboard-table td {
  color: var(--tf-text-muted, #64727D);
}

.tf-dashboard-badge {
  display: inline-flex;
  align-items: center;
  padding: .16rem .42rem;
  border-radius: var(--tf-radius-pill, 999px);
  background: var(--tf-badge-bg, #E7F0EC);
  color: var(--tf-badge-text, #173F35);
  font-size: .76rem;
  font-weight: 620;
}

.tf-dashboard-badge.warning {
  background: var(--tf-state-warning-bg, #FFF3D4);
  color: var(--tf-state-warning-text, #8A6400);
}

.tf-dashboard-badge.danger {
  background: var(--tf-state-danger-bg, #FDE7E7);
  color: var(--tf-state-danger-text, #C62828);
}

.tf-dashboard-empty {
  padding: .85rem;
  border-radius: var(--tf-radius-sm, .5rem);
  background: var(--tf-bg-hover, #EAF1F5);
  color: var(--tf-text-muted, #64727D);
  font-weight: 500;
}

@media (max-width: 1150px) {
  .tf-dashboard-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .tf-dashboard-hero,
  .tf-dashboard-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 680px) {
  .tf-dashboard-grid,
  .tf-dashboard-metrics {
    grid-template-columns: 1fr;
  }
}
CSS);

    $layoutFile = $root . '/app/Admin/AdminLayout.php';

    if (file_exists($layoutFile)) {
        $layout = file_get_contents($layoutFile);

        if (!str_contains($layout, 'dashboard.css')) {
            $layout = str_replace(
                '<link rel="stylesheet" href="/assets/css/media.css">',
                '<link rel="stylesheet" href="/assets/css/media.css">'
                . '<link rel="stylesheet" href="/assets/css/dashboard.css">',
                $layout
            );

            $write($layoutFile, $layout);
        }
    }

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

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function iconSvg(string $name): string
{
    $icons = [
        'pages' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v6h5"/><path d="M9 13h7M9 17h5"/></svg>',
        'media' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M21 16l-5-5-4 4-2-2-5 5"/></svg>',
        'archive' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 7h16v13H4z"/><path d="M3 4h18v3H3z"/><path d="M9 11h6"/></svg>',
        'templates' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2 2-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V20h-3v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-2-2 .1-.1A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.5-1H3v-3h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2-2 .1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V4h3v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 2 2-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1h.1v3h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>',
        'security' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/><path d="M9 12l2 2 4-5"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'docs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/></svg>',
    ];

    return $icons[$name] ?? $icons['pages'];
}

$pageCount = countFiles($root . '/storage/workspaces/published/pages/*.json');
$archiveCount = countFiles($root . '/storage/workspaces/archive/pages/home/*.json');
$docCount = countFiles($root . '/docs/treeforge/*.md');
$mediaCount = countFiles($root . '/storage/media/meta/*.json') + countFiles($root . '/storage/media/meta/*/*.json') + countFiles($root . '/storage/media/meta/*/*/*.json');

$gitTag = trim((string)@shell_exec('git -C ' . escapeshellarg($root) . ' describe --tags --abbrev=0 2>NUL'));
$gitCommit = trim((string)@shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD 2>NUL'));

$storageDriver = (string)($data['storage']['driver'] ?? 'file');
$wafEnabled = (bool)($data['security']['waf_enabled'] ?? false);
$analyticsEnabled = (bool)($data['analytics']['enabled'] ?? false);
$geo = (array)($data['security']['geo_blocking'] ?? []);
$geoEnabled = (bool)($geo['enabled'] ?? false);

$tiles = [
    ['Pages', '/admin/page-settings/', 'pages', $pageCount . ' Seiten', 'RootNode, SEO, Routing, Visibility'],
    ['Media', '/admin/media/', 'media', $mediaCount . ' Medien', 'Originale, Meta, Cache, Presets'],
    ['Archives', '/archives', 'archive', $archiveCount . ' Archive', 'Versionen ansehen und wiederherstellen'],
    ['Templates', '#', 'templates', 'geplant', 'Themes, Presets, Site Packages'],
    ['Settings', '/admin/settings/', 'settings', 'aktiv', 'System, Sprache, Storage, Image Presets'],
    ['Security', '#security', 'security', $wafEnabled ? 'WAF geplant/aktiv' : 'WAF aus', 'Mini-WAF, Geo-Blocking, Logs'],
    ['Users', '#', 'users', 'geplant', 'Benutzer, Rollen, Rechte'],
    ['Docs', '/docs-viewer/', 'docs', $docCount . ' Dokumente', 'Architektur und Roadmap'],
];

$tileHtml = '';

foreach ($tiles as [$label, $href, $icon, $stat, $desc]) {
    $tileHtml .= '<a class="tf-dashboard-tile" href="' . e($href) . '">'
        . '<span class="tf-dashboard-icon">' . iconSvg($icon) . '</span>'
        . '<span>'
        . '<h3>' . e($label) . '</h3>'
        . '<p>' . e($desc) . '</p>'
        . '<small>' . e($stat) . '</small>'
        . '</span>'
        . '</a>';
}

$content = ''
    . '<section class="tf-dashboard-hero">'
    . '<div class="tf-dashboard-welcome">'
    . '<h2>Willkommen bei TreeForge</h2>'
    . '<p>TreeForge Classic Dashboard mit Schnellzugriff, Webstatistik-Platzhaltern, Security Overview und Systemstatus. Die echten Logger und WAF-Funktionen werden später an diese Bereiche angebunden.</p>'
    . '</div>'
    . '<aside class="tf-dashboard-status">'
    . '<h3>Systemstatus</h3>'
    . '<div class="tf-status-list">'
    . '<div class="tf-status-row"><span><i class="tf-dot"></i>PHP</span><strong>' . e(PHP_VERSION) . '</strong></div>'
    . '<div class="tf-status-row"><span><i class="tf-dot"></i>Storage</span><strong>' . e($storageDriver) . '</strong></div>'
    . '<div class="tf-status-row"><span><i class="tf-dot warning"></i>WAF</span><strong>' . e($wafEnabled ? 'vorbereitet' : 'aus') . '</strong></div>'
    . '<div class="tf-status-row"><span><i class="tf-dot warning"></i>Analytics</span><strong>' . e($analyticsEnabled ? 'vorbereitet' : 'aus') . '</strong></div>'
    . '<div class="tf-status-row"><span><i class="tf-dot"></i>Git</span><strong>' . e($gitTag ?: $gitCommit ?: 'unknown') . '</strong></div>'
    . '</div>'
    . '</aside>'
    . '</section>'

    . '<section class="tf-dashboard-grid">'
    . $tileHtml
    . '</section>'

    . '<section class="tf-dashboard-row">'
    . '<div class="tf-dashboard-section">'
    . '<h3>Webstatistik</h3>'
    . '<div class="tf-dashboard-metrics">'
    . '<div class="tf-metric"><strong>0</strong><span>Besucher heute</span></div>'
    . '<div class="tf-metric"><strong>0</strong><span>7 Tage</span></div>'
    . '<div class="tf-metric"><strong>0</strong><span>404 Fehler</span></div>'
    . '</div>'
    . '<div class="tf-dashboard-empty">Analytics Logger noch nicht aktiv. Vorgesehen: lokale Statistik mit IP-Hash, Top-Seiten, Bots, Referrer und 404-Monitor.</div>'
    . '</div>'

    . '<div id="security" class="tf-dashboard-section">'
    . '<h3>Security Overview</h3>'
    . '<div class="tf-dashboard-metrics">'
    . '<div class="tf-metric"><strong>0</strong><span>Blockierte Requests</span></div>'
    . '<div class="tf-metric"><strong>0</strong><span>Login-Versuche</span></div>'
    . '<div class="tf-metric"><strong>0</strong><span>WP-Probes</span></div>'
    . '</div>'
    . '<table class="tf-dashboard-table">'
    . '<thead><tr><th>Modul</th><th>Status</th><th>Hinweis</th></tr></thead>'
    . '<tbody>'
    . '<tr><td>Mini-WAF</td><td><span class="tf-dashboard-badge warning">geplant</span></td><td>Prüfung vor Routing</td></tr>'
    . '<tr><td>Geo-Blocking</td><td><span class="tf-dashboard-badge ' . ($geoEnabled ? '' : 'warning') . '">' . e($geoEnabled ? 'aktiv' : 'log only') . '</span></td><td>Modus: ' . e((string)($geo['mode'] ?? 'log')) . '</td></tr>'
    . '<tr><td>Security Log</td><td><span class="tf-dashboard-badge warning">geplant</span></td><td>Events, Länder, Bot-Probes</td></tr>'
    . '</tbody>'
    . '</table>'
    . '</div>'
    . '</section>'

    . '<section class="tf-dashboard-row">'
    . '<div class="tf-dashboard-section">'
    . '<h3>Geo-Blocking Planung</h3>'
    . '<table class="tf-dashboard-table">'
    . '<tbody>'
    . '<tr><th>Erlaubte Länder</th><td>' . e(implode(', ', (array)($geo['allow_countries'] ?? []))) . '</td></tr>'
    . '<tr><th>Log-only Länder</th><td>' . e(implode(', ', (array)($geo['log_only_countries'] ?? []))) . '</td></tr>'
    . '<tr><th>Blockierte Länder</th><td>' . e(implode(', ', (array)($geo['block_countries'] ?? []))) . '</td></tr>'
    . '<tr><th>Unbekannt</th><td>' . e((string)($geo['unknown_country_action'] ?? 'log')) . '</td></tr>'
    . '</tbody>'
    . '</table>'
    . '</div>'
    . '<div class="tf-dashboard-section">'
    . '<h3>Letzte Security Events</h3>'
    . '<div class="tf-dashboard-empty">Noch keine Security Events vorhanden. Später erscheinen hier blockierte Requests, Länder, Pfade und Gründe.</div>'
    . '</div>'
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

    $write($root . '/docs/treeforge/36-classic-dashboard-overview.md', <<<'MD'
# Classic Dashboard Overview

Patch 046 erweitert das Dashboard im TreeForge Classic Stil.

## Bereiche

```text
Quick Tiles
Webstatistik
Security Overview
Geo-Blocking Planung
Systemstatus
```

## Noch keine Echtzeitdaten

Die Bereiche Webstatistik und Security sind zunächst Platzhalter.

Später werden dort angebunden:

- Analytics Logger
- 404 Monitor
- Mini-WAF
- Security Log
- Geo-Blocking
- Login Protection

## Geo-Blocking Settings

In `settings.json` wird vorbereitet:

```json
{
  "security": {
    "geo_blocking": {
      "enabled": false,
      "mode": "log",
      "allow_countries": ["DE", "AT", "CH"],
      "block_countries": [],
      "log_only_countries": ["CN", "RU", "BY", "KP", "IR"],
      "unknown_country_action": "log"
    }
  }
}
```

## Prinzip

Geo-Blocking soll konfigurierbar sein:

```text
log
challenge
block
```

Nicht hart im Code.
MD);

    $log('Patch 046 Classic Dashboard Overview fertig');
};
