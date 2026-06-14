<?php
declare(strict_types=1);

namespace TreeForge\Core\Icons;

final class IconRenderer
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(private string $root)
    {
        $this->root = rtrim($this->root, '/\\');
        $this->config = $this->loadConfig();
    }

    /** @return array<int,string> */
    public function cssUrls(): array
    {
        if (!$this->truthy($this->config['enabled'] ?? true)) {
            return [];
        }

        $libraries = is_array($this->config['libraries'] ?? null) ? $this->config['libraries'] : [];
        $urls = [];

        foreach ($libraries as $library) {
            if (!is_array($library) || !$this->truthy($library['enabled'] ?? false)) {
                continue;
            }

            $css = is_array($library['css'] ?? null) ? $library['css'] : [];
            foreach ($css as $url) {
                $url = trim((string)$url);
                if ($this->safeCssUrl($url) && !in_array($url, $urls, true)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    public function render(string $icon, string $label = ''): string
    {
        $icon = trim($icon);
        if ($icon === '') {
            return '';
        }

        $label = trim($label);
        $classes = $this->classesFor($icon);

        if ($classes !== []) {
            $class = htmlspecialchars('tf-icon ' . implode(' ', $classes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $aria = $label !== ''
                ? ' aria-label="' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" role="img"'
                : ' aria-hidden="true"';

            return '<i class="' . $class . '"' . $aria . '></i>';
        }

        // Fallback für Emoji oder kurze Textzeichen wie "→".
        return '<span class="tf-icon tf-icon-text" aria-hidden="true">'
            . htmlspecialchars($icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</span>';
    }

    /** @return array<int,string> */
    private function classesFor(string $icon): array
    {
        $raw = trim($icon);
        $lower = strtolower($raw);

        // Bootstrap Icons: bi:house oder bootstrap:house
        if (preg_match('/^(bi|bootstrap):([a-z0-9][a-z0-9-]*)$/i', $raw, $m)) {
            return ['bi', 'bi-' . strtolower($m[2])];
        }

        // Font Awesome: fa:house, fas:house, far:user, fab:github
        if (preg_match('/^(fa|fas|solid):([a-z0-9][a-z0-9-]*)$/i', $raw, $m)) {
            return ['fa-solid', 'fa-' . strtolower($m[2])];
        }
        if (preg_match('/^(far|regular):([a-z0-9][a-z0-9-]*)$/i', $raw, $m)) {
            return ['fa-regular', 'fa-' . strtolower($m[2])];
        }
        if (preg_match('/^(fab|brand|brands):([a-z0-9][a-z0-9-]*)$/i', $raw, $m)) {
            return ['fa-brands', 'fa-' . strtolower($m[2])];
        }

        // Direkte Bootstrap-Icon-Klasse: bi-house
        if (preg_match('/^bi-[a-z0-9][a-z0-9-]*$/i', $raw)) {
            return ['bi', strtolower($raw)];
        }

        // Direkte FontAwesome-Klasse: fa-house
        if (preg_match('/^fa-[a-z0-9][a-z0-9-]*$/i', $raw)) {
            return ['fa-solid', strtolower($raw)];
        }

        // Komplettes Klassen-Set: "bi bi-house" oder "fa-solid fa-house"
        if (str_contains($raw, ' ')) {
            $parts = preg_split('/\s+/', $raw) ?: [];
            $classes = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $part)) {
                    $classes[] = $part;
                }
            }

            $hasKnownPrefix = false;
            foreach ($classes as $class) {
                $lc = strtolower($class);
                if ($lc === 'bi' || str_starts_with($lc, 'bi-') || $lc === 'fa' || str_starts_with($lc, 'fa-')) {
                    $hasKnownPrefix = true;
                    break;
                }
            }

            if ($hasKnownPrefix) {
                return array_values(array_unique($classes));
            }
        }

        // Sicherheitsfallback: normale Wörter nicht als CSS-Klassen interpretieren.
        if (in_array($lower, ['home', 'house', 'mail', 'email', 'github', 'phone'], true)) {
            return ['bi', 'bi-' . ($lower === 'email' ? 'envelope' : $lower)];
        }

        return [];
    }

    /** @return array<string,mixed> */
    private function loadConfig(): array
    {
        $file = $this->root . '/storage/system/icon-libraries.json';
        if (is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'enabled' => true,
            'default_library' => 'bootstrap-icons',
            'libraries' => [
                'bootstrap-icons' => [
                    'enabled' => true,
                    'css' => ['https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'],
                ],
                'fontawesome' => [
                    'enabled' => true,
                    'css' => ['https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'],
                ],
            ],
        ];
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string)$value));
        return !in_array($value, ['', '0', 'false', 'no', 'nein', 'off', 'disabled'], true);
    }

    private function safeCssUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return !str_contains($url, '..');
        }

        return (bool)preg_match('#^https://[a-z0-9][a-z0-9.-]+/#i', $url);
    }
}