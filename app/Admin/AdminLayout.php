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
            . '<link rel="stylesheet" href="/assets/css/tf-theme-vars.css">'
            . '<link rel="stylesheet" href="/assets/css/admin.css">'
            . '<link rel="stylesheet" href="/assets/css/page-settings.css">'
            . '<link rel="stylesheet" href="/assets/css/media.css"><link rel="stylesheet" href="/assets/css/dashboard.css">'
            . '</head>'
            . '<body>'
            . '<header class="tf-admin-topnav">'
            . '<a class="tf-admin-brand" href="/admin/">TreeForge CMS</a>'
            . '<nav class="tf-admin-mainnav">'
            . $this->mainNav($active)
            . '</nav>'
            . '<div class="tf-admin-user"><strong>dscho</strong><span>Superadministrator</span></div>'
            . '</header>'
            . '<main class="tf-admin-page">'
            . '<section class="tf-admin-page-head">'
            . '<div>'
            . '<div class="tf-admin-kicker">' . $this->e($subtitle) . '</div>'
            . '<h1>' . $this->e($title) . '</h1>'
            . '</div>'
            . '<nav class="tf-admin-quicklinks">'
            . '<a href="/" target="_blank" rel="noopener">Frontend</a>'
            . '<a href="https://github.com/johannes-teitge/treeforge" target="_blank" rel="noopener">GitHub</a>'
            . '</nav>'
            . '</section>'
            . '<section class="tf-admin-content">'
            . $content
            . '</section>'
            . '</main>'
            . '<script src="/assets/js/media-picker.js"></script><script src="/assets/js/page-settings-media-picker.js"></script></body>'
            . '</html>';
    }

    protected function mainNav(string $active): string
    {
        $html = '';

        foreach (AdminMenu::items() as $item) {
            $disabled = !empty($item['disabled']);
            $key = (string)($item['key'] ?? '');
            $isActive = $key === $active;
            $class = 'tf-admin-nav-link'
                . ($isActive ? ' active' : '')
                . ($disabled ? ' disabled' : '');

            $href = $disabled ? '#' : (string)$item['href'];
            $label = (string)$item['label'];

            if ($key === 'page-settings') {
                $label = 'Pages';
            }

            if ($key === 'explorer') {
                $label = 'Explorer';
            }

            $html .= '<a class="' . $class . '" href="' . $this->e($href) . '">'
                . $this->e($label)
                . ($disabled ? '<small>geplant</small>' : '')
                . '</a>';
        }

        return $html;
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}