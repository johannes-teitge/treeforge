<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 047
 * Fix SettingsManager Syntax
 *
 * Problem:
 * Patch 046 hat beim automatischen Einfügen der geo_blocking Settings
 * die defaults()-Array-Struktur in SettingsManager.php beschädigt.
 *
 * Fehler:
 * Fatal error: Cannot use empty array elements in arrays
 *
 * Fix:
 * SettingsManager.php wird sauber und vollständig neu geschrieben.
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

    $log('Patch 047 Fix SettingsManager Syntax gestartet');

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

                'image_presets' => [
                    'thumbnail' => [
                        'label' => 'Thumbnail',
                        'width' => 300,
                        'height' => 300,
                        'mode' => 'cover',
                        'format' => 'webp',
                        'quality' => 82,
                        'locked' => false,
                    ],
                    'card' => [
                        'label' => 'Card',
                        'width' => 600,
                        'height' => null,
                        'mode' => 'contain',
                        'format' => 'webp',
                        'quality' => 82,
                        'locked' => false,
                    ],
                    'content' => [
                        'label' => 'Content',
                        'width' => 900,
                        'height' => null,
                        'mode' => 'contain',
                        'format' => 'webp',
                        'quality' => 82,
                        'locked' => false,
                    ],
                    'content-large' => [
                        'label' => 'Content Large',
                        'width' => 1400,
                        'height' => null,
                        'mode' => 'contain',
                        'format' => 'webp',
                        'quality' => 84,
                        'locked' => false,
                    ],
                    'hero' => [
                        'label' => 'Hero',
                        'width' => 1920,
                        'height' => null,
                        'mode' => 'contain',
                        'format' => 'webp',
                        'quality' => 84,
                        'locked' => false,
                    ],
                    'social' => [
                        'label' => 'Social',
                        'width' => 1200,
                        'height' => 630,
                        'mode' => 'cover',
                        'format' => 'jpg',
                        'quality' => 90,
                        'locked' => true,
                    ],
                ],

                'render_cache' => [
                    'enabled' => true,
                    'keep_originals' => true,
                    'cache_dir' => 'storage/media/cache',
                    'auto_generate_on_upload' => false,
                    'generate_on_demand' => true,
                ],
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

                'geo_blocking' => [
                    'enabled' => false,
                    'mode' => 'log',
                    'allow_countries' => ['DE', 'AT', 'CH'],
                    'block_countries' => [],
                    'log_only_countries' => ['CN', 'RU', 'BY', 'KP', 'IR'],
                    'unknown_country_action' => 'log',
                ],
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
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && !$this->isList($value)
            ) {
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

    $write($root . '/docs/treeforge/37-fix-settings-manager-syntax.md', <<<'MD'
# Fix SettingsManager Syntax

Patch 047 repariert einen Syntaxfehler in `SettingsManager.php`.

## Ursache

Beim automatischen Einfügen der `geo_blocking` Settings wurde die Array-Struktur in `SettingsManager::defaults()` beschädigt.

## Fehler

```text
Fatal error: Cannot use empty array elements in arrays
```

## Fix

`SettingsManager.php` wurde sauber neu geschrieben.

Enthalten sind jetzt:

- General
- Languages
- Storage
- Editor
- Media
- Image Presets
- Render Cache
- Security
- Geo Blocking
- Analytics
- Updates
- Developer
MD);

    $log('Patch 047 Fix SettingsManager Syntax fertig');
};
