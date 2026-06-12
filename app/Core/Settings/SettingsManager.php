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
                'max_files_per_upload' => 20,
                'normalize_filenames' => true,
                'unique_filenames' => true,
                'drag_drop_enabled' => true,
                'chunk_upload_enabled' => false,
                'chunk_size_mb' => 5,
                'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf'],

                'file_types' => [
                    'images' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],
                    'documents' => ['pdf', 'docx', 'xlsx', 'txt', 'csv', 'odt'],
                    'downloads' => ['zip'],
                    'audio' => [],
                    'video' => [],
                ],

                'svg' => [
                    'allow_upload' => true,
                    'sanitize' => true,
                    'allow_as_image' => true,
                    'allow_as_logo' => true,
                    'allow_as_icon' => true,
                    'allow_as_social_image' => false,
                    'show_social_warning' => true,
                ],

                'zip' => [
                    'allow_upload' => true,
                    'allow_as_download' => true,
                    'allow_site_package' => true,
                    'allow_extract' => false,
                    'extract_admin_only' => true,
                    'max_size_mb' => 50,
                ],

                'downloads' => [
                    'enabled' => true,
                    'max_size_mb' => 50,
                    'force_download_default' => false,
                ],

                'accessibility' => [
                    'require_alt_for_images' => false,
                    'warn_missing_alt' => true,
                    'require_title' => false,
                ],

                'replace' => [
                    'enabled' => true,
                    'keep_media_id' => true,
                    'keep_old_versions' => true,
                    'max_versions' => 10,
                    'invalidate_cache_on_replace' => true,
                ],

                'security' => [
                    'scan_upload_names' => true,
                    'block_php_files' => true,
                    'block_double_extensions' => true,
                    'block_executable_mime' => true,
                    'strip_exif' => true,
                ],

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
                    'clear_unused_after_days' => 90,
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