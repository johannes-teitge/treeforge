<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 037
 * Settings Foundation
 *
 * Ziel:
 * - zentrale settings.json anlegen
 * - SettingsManager einführen
 * - erste Backend-Seite /admin/settings
 * - Tabs: General, Languages, Storage, System Info
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

    $writeIfMissing = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        if (file_exists($file)) {
            $log("Datei existiert bereits, übersprungen: {$file}");
            return;
        }
        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 037 Settings Foundation gestartet');

    $write($root . '/app/Core/Settings/SettingsManager.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core\Settings;

class SettingsManager
{
    protected string $file;
    protected array $settings = [];

    public function __construct(protected string $root)
    {
        $this->file = $this->root . '/storage/system/settings.json';
        $this->settings = $this->load();
    }

    public function all(): array
    {
        return $this->settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->settings;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &$this->settings;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }

        $target = $value;
    }

    public function merge(array $values): void
    {
        $this->settings = $this->arrayMergeRecursiveDistinct($this->settings, $values);
    }

    public function save(): void
    {
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0775, true);
        }

        file_put_contents(
            $this->file,
            json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function load(): array
    {
        $defaults = self::defaults();

        if (!file_exists($this->file)) {
            return $defaults;
        }

        $data = json_decode((string)file_get_contents($this->file), true);

        if (!is_array($data)) {
            return $defaults;
        }

        return $this->arrayMergeRecursiveDistinct($defaults, $data);
    }

    public static function defaults(): array
    {
        return [
            'general' => [
                'site_name' => 'TreeForge CMS',
                'site_url' => 'http://localhost',
                'admin_email' => '',
                'timezone' => 'Europe/Berlin',
            ],
            'languages' => [
                'default_language' => 'de',
                'enabled_languages' => ['de'],
                'multilanguage' => false,
            ],
            'storage' => [
                'driver' => 'file',
                'database_path' => 'storage/database/treeforge.sqlite',
            ],
            'editor' => [
                'default_workspace' => 'draft',
                'autosave' => false,
                'archive_limit' => 50,
                'allow_html_node' => true,
                'allow_raw_script' => false,
            ],
            'media' => [
                'upload_path' => 'storage/media',
                'public_path' => '/media',
                'max_file_size_mb' => 10,
                'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf'],
            ],
            'security' => [
                'waf_enabled' => true,
                'rate_limit_enabled' => true,
                'max_requests_per_minute' => 120,
                'block_wordpress_probes' => true,
                'block_sqli_patterns' => true,
                'block_xss_patterns' => true,
                'login_protection' => true,
                'retention_days' => 30,
            ],
            'analytics' => [
                'enabled' => true,
                'anonymize_ip' => true,
                'respect_do_not_track' => true,
                'retention_days' => 30,
                'count_bots' => true,
            ],
            'updates' => [
                'channel' => 'alpha',
                'update_server' => 'https://treeforge.de/api/updates',
                'auto_update' => false,
                'verify_signature' => true,
            ],
            'developer' => [
                'debug' => true,
                'custom_nodes_enabled' => true,
                'cache_enabled' => false,
                'maintenance_mode' => false,
            ],
        ];
    }

    protected function arrayMergeRecursiveDistinct(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !$this->isList($value)) {
                $base[$key] = $this->arrayMergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    protected function isList(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}
PHP);

    $writeIfMissing($root . '/storage/system/settings.json', <<<'JSON'
{
    "general": {
        "site_name": "TreeForge CMS",
        "site_url": "http://localhost",
        "admin_email": "",
        "timezone": "Europe/Berlin"
    },
    "languages": {
        "default_language": "de",
        "enabled_languages": [
            "de"
        ],
        "multilanguage": false
    },
    "storage": {
        "driver": "file",
        "database_path": "storage/database/treeforge.sqlite"
    },
    "editor": {
        "default_workspace": "draft",
        "autosave": false,
        "archive_limit": 50,
        "allow_html_node": true,
        "allow_raw_script": false
    },
    "media": {
        "upload_path": "storage/media",
        "public_path": "/media",
        "max_file_size_mb": 10,
        "allowed_types": [
            "jpg",
            "jpeg",
            "png",
            "webp",
            "svg",
            "pdf"
        ]
    },
    "security": {
        "waf_enabled": true,
        "rate_limit_enabled": true,
        "max_requests_per_minute": 120,
        "block_wordpress_probes": true,
        "block_sqli_patterns": true,
        "block_xss_patterns": true,
        "login_protection": true,
        "retention_days": 30
    },
    "analytics": {
        "enabled": true,
        "anonymize_ip": true,
        "respect_do_not_track": true,
        "retention_days": 30,
        "count_bots": true
    },
    "updates": {
        "channel": "alpha",
        "update_server": "https://treeforge.de/api/updates",
        "auto_update": false,
        "verify_signature": true
    },
    "developer": {
        "debug": true,
        "custom_nodes_enabled": true,
        "cache_enabled": false,
        "maintenance_mode": false
    }
}
JSON);

    $write($root . '/public/admin/settings/index.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

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

?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Settings · TreeForge</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="/assets/css/settings.css">
</head>
<body>
  <header class="tf-settings-header">
    <div>
      <a href="/explorer" class="tf-settings-brand">TREE<span>FORGE</span></a>
      <p>Zentrale Systemeinstellungen</p>
    </div>
    <nav>
      <a href="/explorer">Explorer</a>
      <a href="/archives">Archive</a>
      <a href="/docs-viewer/">Docs</a>
    </nav>
  </header>

  <main class="tf-settings-shell">
    <?php if ($saved): ?>
      <div class="tf-notice success">Einstellungen wurden gespeichert.</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="tf-notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="tf-settings-form">
      <aside class="tf-settings-tabs" aria-label="Settings Navigation">
        <a href="#general">General</a>
        <a href="#languages">Languages</a>
        <a href="#storage">Storage</a>
        <a href="#system">System Info</a>
      </aside>

      <section class="tf-settings-content">
        <section id="general" class="tf-settings-card">
          <h1>General</h1>
          <p>Grunddaten der Website und Umgebung.</p>

          <label><span>Site Name</span><input type="text" name="general[site_name]" value="<?= e($data['general']['site_name'] ?? '') ?>"></label>
          <label><span>Site URL</span><input type="url" name="general[site_url]" value="<?= e($data['general']['site_url'] ?? '') ?>"></label>
          <label><span>Admin E-Mail</span><input type="email" name="general[admin_email]" value="<?= e($data['general']['admin_email'] ?? '') ?>"></label>
          <label><span>Timezone</span><input type="text" name="general[timezone]" value="<?= e($data['general']['timezone'] ?? 'Europe/Berlin') ?>"></label>
        </section>

        <section id="languages" class="tf-settings-card">
          <h2>Languages</h2>
          <p>Auch ohne Multilanguage wird intern immer eine Default-Sprache gesetzt.</p>

          <label><span>Default Language</span><input type="text" name="languages[default_language]" value="<?= e($data['languages']['default_language'] ?? 'de') ?>"></label>
          <label><span>Enabled Languages</span><input type="text" name="languages[enabled_languages]" value="<?= e(implode(',', (array)($data['languages']['enabled_languages'] ?? ['de']))) ?>"><small>Kommagetrennt, z. B. de,en,fr</small></label>
          <label class="tf-check"><input type="checkbox" name="languages[multilanguage]" value="1"<?= checked((bool)($data['languages']['multilanguage'] ?? false)) ?>><span>Multilanguage aktivieren</span></label>
        </section>

        <section id="storage" class="tf-settings-card">
          <h2>Storage</h2>
          <p>Aktuell arbeitet TreeForge mit FileStorage. SQLite/MySQL sind vorbereitet.</p>

          <label>
            <span>Storage Driver</span>
            <select name="storage[driver]">
              <option value="file"<?= selected((string)($data['storage']['driver'] ?? 'file'), 'file') ?>>File</option>
              <option value="sqlite"<?= selected((string)($data['storage']['driver'] ?? 'file'), 'sqlite') ?>>SQLite</option>
              <option value="mysql"<?= selected((string)($data['storage']['driver'] ?? 'file'), 'mysql') ?>>MySQL</option>
            </select>
          </label>

          <label><span>SQLite Database Path</span><input type="text" name="storage[database_path]" value="<?= e($data['storage']['database_path'] ?? 'storage/database/treeforge.sqlite') ?>"></label>
          <div class="tf-warning">Der Storage-Treiber wird aktuell nur gespeichert. Eine aktive Umschaltung erfolgt erst mit dem späteren StorageInterface-Patch.</div>
        </section>

        <section id="system" class="tf-settings-card">
          <h2>System Info</h2>
          <p>Erste technische Übersicht für spätere Support- und Diagnosefunktionen.</p>

          <dl class="tf-system-info">
            <dt>TreeForge Version</dt><dd><?= e($version) ?></dd>
            <dt>PHP Version</dt><dd><?= e(PHP_VERSION) ?></dd>
            <dt>Storage Driver</dt><dd><?= e($data['storage']['driver'] ?? 'file') ?></dd>
            <dt>Default Language</dt><dd><?= e($data['languages']['default_language'] ?? 'de') ?></dd>
            <dt>Git Tag</dt><dd><?= e($gitTag ?: 'unknown') ?></dd>
            <dt>Git Commit</dt><dd><?= e($gitCommit ?: 'unknown') ?></dd>
            <dt>Settings File</dt><dd><code>storage/system/settings.json</code></dd>
          </dl>
        </section>

        <div class="tf-settings-savebar"><button type="submit">Einstellungen speichern</button></div>
      </section>
    </form>
  </main>
</body>
</html>
PHP);

    $write($root . '/public/assets/css/settings.css', <<<'CSS'
:root{--tf-green:#173F35;--tf-gold:#D88A22;--tf-dark:#121A17;--tf-light:#F5F3EA;--tf-cream:#FFFAF0;--tf-border:rgba(23,63,53,.14);--tf-muted:#66756e}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--tf-light);color:var(--tf-dark);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.tf-settings-header{min-height:84px;padding:1rem 1.4rem;background:rgba(255,250,240,.94);border-bottom:1px solid var(--tf-border);display:flex;align-items:center;justify-content:space-between;gap:1rem;position:sticky;top:0;z-index:20;backdrop-filter:blur(12px)}.tf-settings-brand{color:var(--tf-green);text-decoration:none;font-weight:950;letter-spacing:.08em;font-size:1.2rem}.tf-settings-brand span{color:var(--tf-gold)}.tf-settings-header p{margin:.2rem 0 0;color:var(--tf-muted)}.tf-settings-header nav{display:flex;flex-wrap:wrap;gap:.5rem}.tf-settings-header nav a{display:inline-flex;padding:.55rem .75rem;border-radius:.75rem;background:#fff;border:1px solid var(--tf-border);color:var(--tf-green);text-decoration:none;font-weight:800}.tf-settings-shell{padding:1rem}.tf-notice{max-width:1200px;margin:0 auto 1rem;padding:.85rem 1rem;border-radius:.85rem;font-weight:800}.tf-notice.success{background:rgba(30,120,60,.14);color:#17572c;border:1px solid rgba(30,120,60,.22)}.tf-notice.error{background:rgba(138,59,20,.12);color:#8a3b14;border:1px solid rgba(138,59,20,.22)}.tf-settings-form{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:260px minmax(0,1fr);gap:1rem}.tf-settings-tabs{background:var(--tf-cream);border:1px solid var(--tf-border);border-radius:1.1rem;padding:.75rem;height:max-content;position:sticky;top:104px}.tf-settings-tabs a{display:block;padding:.75rem .85rem;color:var(--tf-dark);text-decoration:none;border-radius:.8rem;font-weight:800;margin-bottom:.25rem}.tf-settings-tabs a:hover{background:rgba(216,138,34,.14);color:var(--tf-green)}.tf-settings-content{display:grid;gap:1rem}.tf-settings-card{background:var(--tf-cream);border:1px solid var(--tf-border);border-radius:1.1rem;padding:clamp(1rem,3vw,2rem);box-shadow:0 1rem 2.8rem rgba(18,26,23,.05)}.tf-settings-card h1,.tf-settings-card h2{margin:0 0 .35rem;color:var(--tf-green)}.tf-settings-card p{margin:0 0 1.25rem;color:var(--tf-muted)}.tf-settings-card label{display:grid;gap:.35rem;margin-bottom:1rem;font-weight:850;color:var(--tf-green)}.tf-settings-card input,.tf-settings-card select{width:100%;border:1px solid rgba(23,63,53,.22);border-radius:.8rem;padding:.75rem .85rem;font:inherit;background:#fff;color:var(--tf-dark)}.tf-settings-card small{color:var(--tf-muted);font-weight:650}.tf-check{display:flex!important;grid-template-columns:none!important;align-items:center;gap:.7rem!important}.tf-check input{width:auto;transform:scale(1.2)}.tf-warning{padding:.9rem 1rem;border-radius:.9rem;background:rgba(216,138,34,.14);border:1px solid rgba(216,138,34,.22);color:#76470d;font-weight:800}.tf-system-info{display:grid;grid-template-columns:190px minmax(0,1fr);gap:.65rem 1rem;margin:0}.tf-system-info dt{color:var(--tf-green);font-weight:900}.tf-system-info dd{margin:0;color:#2f3b36;overflow-wrap:anywhere}.tf-system-info code{background:rgba(23,63,53,.08);padding:.15rem .35rem;border-radius:.35rem}.tf-settings-savebar{position:sticky;bottom:0;background:rgba(245,243,234,.92);border:1px solid var(--tf-border);border-radius:1rem;padding:.8rem;display:flex;justify-content:flex-end;backdrop-filter:blur(12px)}.tf-settings-savebar button{border:0;background:var(--tf-green);color:#fff;padding:.8rem 1rem;border-radius:.8rem;font:inherit;font-weight:900;cursor:pointer}.tf-settings-savebar button:hover{background:#0f2d26}@media(max-width:900px){.tf-settings-form{grid-template-columns:1fr}.tf-settings-tabs{position:static}.tf-settings-header{position:static;flex-direction:column;align-items:flex-start}.tf-system-info{grid-template-columns:1fr}}
CSS);

    $write($root . '/docs/treeforge/27-settings-foundation.md', <<<'MD'
# Settings Foundation

Patch 037 ergänzt die erste zentrale Settings-Struktur.

## Dateien

```text
app/Core/Settings/SettingsManager.php
storage/system/settings.json
public/admin/settings/index.php
public/assets/css/settings.css
```

## Route

```text
/admin/settings
```

Falls Rewrite nicht greift:

```text
/admin/settings/index.php
```

## Erste Tabs

```text
General
Languages
Storage
System Info
```

## Zweck

TreeForge bekommt damit eine zentrale Stelle für Systemwerte.

Spätere Bereiche wie Security, Analytics, Updates, Media, Templates und Developer können dort schrittweise ergänzt werden.

## Hinweis

Der Storage-Treiber kann bereits gespeichert werden.

Die aktive Umschaltung auf SQLite/MySQL erfolgt erst mit einem späteren StorageInterface-Patch.
MD);

    $log('Patch 037 Settings Foundation fertig');
};
