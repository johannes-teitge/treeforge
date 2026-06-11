<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 052
 * Fix Dashboard Parse Error
 *
 * Problem:
 * Patch 050 hat public/admin/index.php beschädigt.
 *
 * Fehler:
 * Parse error: unexpected token "<", expecting end of file
 *
 * Fix:
 * Dashboard wird vollständig und sauber neu geschrieben.
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

    $log('Patch 052 Fix Dashboard Parse Error gestartet');

    $write($root . '/public/admin/index.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\Settings\SettingsManager;
use TreeForge\Core\System\Version;

$root = dirname(__DIR__, 2);
$settings = new SettingsManager($root);
$versionInfo = new Version($root);
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
$mediaCount = countFiles($root . '/storage/media/meta/*.json')
    + countFiles($root . '/storage/media/meta/*/*.json')
    + countFiles($root . '/storage/media/meta/*/*/*.json');

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
    ['Settings', '/admin/settings/', 'settings', 'aktiv', 'System, Sprache, Storage, Media Settings'],
    ['Security', '#security', 'security', $wafEnabled ? 'vorbereitet' : 'aus', 'Mini-WAF, Geo-Blocking, Logs'],
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
    . '<div class="tf-status-row"><span><i class="tf-dot"></i>Version</span><strong>' . e($versionInfo->version()) . '</strong></div>'
    . '<div class="tf-status-row"><span><i class="tf-dot"></i>Build</span><strong>' . e($versionInfo->build()) . '</strong></div>'
    . '<div class="tf-status-row"><span><i class="tf-dot warning"></i>Update Channel</span><strong>' . e($versionInfo->channel()) . '</strong></div>'
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

    $write($root . '/docs/treeforge/42-fix-dashboard-parse-error.md', <<<'MD'
# Fix Dashboard Parse Error

Patch 052 repariert `public/admin/index.php`.

## Fehler

```text
Parse error: unexpected token "<", expecting end of file
```

## Ursache

Beim automatischen Patchen wurde die Dashboard-Datei beschädigt.

## Fix

Das Dashboard wurde vollständig neu geschrieben.

Enthalten:

- Quick Tiles
- Webstatistik-Platzhalter
- Security Overview
- Geo-Blocking Planung
- Systemstatus mit Version/Build/Channel
MD);

    $log('Patch 052 Fix Dashboard Parse Error fertig');
};
