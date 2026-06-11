<?php
declare(strict_types=1);

namespace TreeForge\Core\PageSettings;

class PageSettingsManager
{
    public function __construct(protected string $root) {}

    public function load(string $workspace = 'published', string $page = 'home'): array
    {
        $file = $this->pageFile($workspace, $page);

        if (!file_exists($file)) {
            return $this->defaults($page);
        }

        $data = json_decode((string)file_get_contents($file), true);

        if (!is_array($data)) {
            return $this->defaults($page);
        }

        return $this->normalize($data, $page);
    }

    public function save(array $pageData, string $workspace = 'published', string $page = 'home'): void
    {
        $file = $this->pageFile($workspace, $page);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        $pageData = $this->normalize($pageData, $page);
        $pageData['updated_at'] = date('c');

        file_put_contents(
            $file,
            json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function applySettings(array $pageData, array $input, string $page = 'home'): array
    {
        $pageData = $this->normalize($pageData, $page);

        $pageData['id'] = $page;
        $pageData['type'] = 'root';

        $pageData['title'] = trim((string)($input['general']['title'] ?? $pageData['title']));
        $pageData['slug'] = trim((string)($input['general']['slug'] ?? $pageData['slug']));
        $pageData['status'] = (string)($input['general']['status'] ?? $pageData['status']);
        $pageData['content_type'] = (string)($input['general']['content_type'] ?? $pageData['content_type']);
        $pageData['template'] = trim((string)($input['general']['template'] ?? $pageData['template']));

        $pageData['seo'] = [
            'meta_title' => trim((string)($input['seo']['meta_title'] ?? '')),
            'meta_description' => trim((string)($input['seo']['meta_description'] ?? '')),
            'keywords' => trim((string)($input['seo']['keywords'] ?? '')),
            'canonical_url' => trim((string)($input['seo']['canonical_url'] ?? '')),
            'robots' => (string)($input['seo']['robots'] ?? 'index,follow'),
        ];

        $pageData['social'] = [
            'og_title' => trim((string)($input['social']['og_title'] ?? '')),
            'og_description' => trim((string)($input['social']['og_description'] ?? '')),
            'og_image' => trim((string)($input['social']['og_image'] ?? '')),
            'twitter_card' => (string)($input['social']['twitter_card'] ?? 'summary_large_image'),
        ];

        $pageData['overview'] = [
            'teaser' => trim((string)($input['overview']['teaser'] ?? '')),
            'excerpt' => trim((string)($input['overview']['excerpt'] ?? '')),
            'featured_image' => trim((string)($input['overview']['featured_image'] ?? '')),
            'featured' => isset($input['overview']['featured']),
        ];

        $pageData['routing'] = [
            'path' => trim((string)($input['routing']['path'] ?? '')),
            'is_home' => isset($input['routing']['is_home']),
            'no_slug' => isset($input['routing']['no_slug']),
            'redirect_from' => $this->linesToArray((string)($input['routing']['redirect_from'] ?? '')),
            'redirect_to' => trim((string)($input['routing']['redirect_to'] ?? '')),
        ];

        $pageData['visibility'] = [
            'active' => isset($input['visibility']['active']),
            'valid_from' => $this->emptyToNull((string)($input['visibility']['valid_from'] ?? '')),
            'valid_until' => $this->emptyToNull((string)($input['visibility']['valid_until'] ?? '')),
            'schedule_enabled' => isset($input['visibility']['schedule_enabled']),
            'schedule' => [
                'days' => array_values((array)($input['visibility']['schedule']['days'] ?? [])),
                'time_from' => trim((string)($input['visibility']['schedule']['time_from'] ?? '')),
                'time_until' => trim((string)($input['visibility']['schedule']['time_until'] ?? '')),
                'timezone' => trim((string)($input['visibility']['schedule']['timezone'] ?? 'Europe/Berlin')),
            ],
            'outside_schedule' => (string)($input['visibility']['outside_schedule'] ?? 'hide'),
        ];

        $pageData['advanced'] = [
            'author' => trim((string)($input['advanced']['author'] ?? '')),
            'editor' => trim((string)($input['advanced']['editor'] ?? '')),
            'created_at' => $this->emptyToNull((string)($input['advanced']['created_at'] ?? ($pageData['advanced']['created_at'] ?? ''))),
            'published_at' => $this->emptyToNull((string)($input['advanced']['published_at'] ?? '')),
            'archived_at' => $this->emptyToNull((string)($input['advanced']['archived_at'] ?? '')),
            'experiments' => (array)($pageData['advanced']['experiments'] ?? []),
        ];

        if (empty($pageData['advanced']['created_at'])) {
            $pageData['advanced']['created_at'] = date('c');
        }

        return $pageData;
    }

    public function normalize(array $pageData, string $page = 'home'): array
    {
        $pageData = $this->merge($this->defaults($page), $pageData);

        $pageData['id'] = (string)($pageData['id'] ?? $page);
        $pageData['type'] = (string)($pageData['type'] ?? 'root');

        if (!isset($pageData['nodes']) && !isset($pageData['children'])) {
            $pageData['nodes'] = [];
        }

        return $pageData;
    }

    public function defaults(string $page = 'home'): array
    {
        return [
            'id' => $page,
            'type' => 'root',
            'title' => $page === 'home' ? 'Startseite' : $page,
            'slug' => $page === 'home' ? '' : $page,
            'status' => 'published',
            'content_type' => 'page',
            'template' => 'default',
            'lang' => 'de',
            'seo' => [
                'meta_title' => '',
                'meta_description' => '',
                'keywords' => '',
                'canonical_url' => '',
                'robots' => 'index,follow',
            ],
            'social' => [
                'og_title' => '',
                'og_description' => '',
                'og_image' => '',
                'twitter_card' => 'summary_large_image',
            ],
            'overview' => [
                'teaser' => '',
                'excerpt' => '',
                'featured_image' => '',
                'featured' => false,
            ],
            'routing' => [
                'path' => $page === 'home' ? '/' : '/' . $page,
                'is_home' => $page === 'home',
                'no_slug' => $page === 'home',
                'redirect_from' => [],
                'redirect_to' => '',
            ],
            'visibility' => [
                'active' => true,
                'valid_from' => null,
                'valid_until' => null,
                'schedule_enabled' => false,
                'schedule' => [
                    'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                    'time_from' => '',
                    'time_until' => '',
                    'timezone' => 'Europe/Berlin',
                ],
                'outside_schedule' => 'hide',
            ],
            'advanced' => [
                'author' => '',
                'editor' => '',
                'created_at' => null,
                'published_at' => null,
                'archived_at' => null,
                'experiments' => [],
            ],
        ];
    }

    protected function pageFile(string $workspace, string $page): string
    {
        $workspace = preg_replace('/[^a-zA-Z0-9_-]/', '', $workspace) ?: 'published';
        $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page) ?: 'home';

        return $this->root . '/storage/workspaces/' . $workspace . '/pages/' . $page . '.json';
    }

    protected function linesToArray(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn(string $line): string => trim($line),
            preg_split('/\R/', $value) ?: []
        )));
    }

    protected function emptyToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !$this->isList($value)) {
                $base[$key] = $this->merge($base[$key], $value);
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