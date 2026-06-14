<?php
declare(strict_types=1);

namespace TreeForge\Core\Navigation;

use TreeForge\Core\Icons\IconRenderer;

final class NavigationManager
{
    private ?IconRenderer $iconRenderer = null;
    public function __construct(
        private string $root,
        private string $workspace = 'published'
    ) {
        $this->root = rtrim($this->root, '/\\');
        $this->workspace = $this->sanitizeWorkspace($this->workspace);
    }

    public function exists(string $menuId): bool
    {
        $menu = $this->load($menuId);
        $items = $menu['items'] ?? [];
        return is_array($items) && $this->hasVisibleItems($items);
    }

    /** @return array<string,mixed> */
    public function load(string $menuId): array
    {
        $menuId = $this->sanitizeMenuId($menuId);
        $file = $this->path($menuId);

        if (!is_file($file) && $this->workspace !== 'published') {
            $published = $this->root . '/storage/workspaces/published/navigation/' . $menuId . '.json';
            if (is_file($published)) {
                $file = $published;
            }
        }

        if (!is_file($file)) {
            return [
                'id' => $menuId,
                'type' => 'navigation',
                'title' => $menuId,
                'items' => [],
            ];
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            return [
                'id' => $menuId,
                'type' => 'navigation',
                'title' => $menuId,
                'items' => [],
            ];
        }

        $data['id'] = (string)($data['id'] ?? $menuId);
        $data['type'] = (string)($data['type'] ?? 'navigation');
        $data['title'] = (string)($data['title'] ?? $data['id']);
        $data['items'] = is_array($data['items'] ?? null) ? $data['items'] : [];

        return $data;
    }

    public function render(string $menuId, string $currentPageId = ''): string
    {
        $menuId = $this->sanitizeMenuId($menuId);
        $menu = $this->load($menuId);
        $items = is_array($menu['items'] ?? null) ? $menu['items'] : [];

        $itemsHtml = $this->renderItems($items, $currentPageId);
        if ($itemsHtml === '') {
            return '';
        }

        $safeMenuId = htmlspecialchars($menuId, ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string)($menu['title'] ?? $menuId), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<nav class="tf-menu tf-menu-{$safeMenuId}" aria-label="{$label}">
  <ul class="tf-menu-list">
    {$itemsHtml}
  </ul>
</nav>
HTML;
    }

    /** @param array<int,mixed> $items */
    private function hasVisibleItems(array $items): bool
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((string)($item['status'] ?? 'active') !== 'active') {
                continue;
            }
            if (trim((string)($item['label'] ?? $item['title'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,mixed> $items */
    private function renderItems(array $items, string $currentPageId): string
    {
        $html = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((string)($item['status'] ?? 'active') !== 'active') {
                continue;
            }

            $label = trim((string)($item['label'] ?? $item['title'] ?? ''));
            if ($label === '') {
                continue;
            }

            $page = trim((string)($item['page'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            $href = $this->resolveHref($page, $url);
            $target = trim((string)($item['target'] ?? '_self'));
            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            $childHtml = $children !== [] ? $this->renderItems($children, $currentPageId) : '';
            $isActive = $page !== '' && $currentPageId !== '' && $page === $currentPageId;

            $classes = ['tf-menu-item'];
            if ($isActive) {
                $classes[] = 'is-active';
            }
            if ($childHtml !== '') {
                $classes[] = 'has-children';
            }

            $safeClass = htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8');
            $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $iconHtml = $this->renderIcon((string)($item['icon'] ?? ''));
            $safeTarget = in_array($target, ['_self', '_blank', '_parent', '_top'], true) ? $target : '_self';
            $targetAttr = $safeTarget !== '_self' ? ' target="' . $safeTarget . '" rel="noopener"' : '';
            $submenu = $childHtml !== ''
                ? "\n    <ul class=\"tf-submenu\">\n      {$childHtml}\n    </ul>"
                : '';

            $html[] = <<<HTML
<li class="{$safeClass}"><a class="tf-menu-link" href="{$safeHref}"{$targetAttr}>{$iconHtml}<span class="tf-menu-label">{$safeLabel}</span></a>{$submenu}</li>
HTML;
        }

        return implode("\n    ", $html);
    }

    private function renderIcon(string $icon): string
    {
        $icon = trim($icon);
        if ($icon === '') {
            return '';
        }

        $file = $this->root . '/app/Core/Icons/IconRenderer.php';
        if (!class_exists(IconRenderer::class) && is_file($file)) {
            require_once $file;
        }

        if ($this->iconRenderer === null) {
            $this->iconRenderer = new IconRenderer($this->root);
        }

        return '<span class="tf-menu-icon" aria-hidden="true">' . $this->iconRenderer->render($icon) . '</span>';
    }
    private function resolveHref(string $page, string $url): string
    {
        if ($url !== '') {
            return $url;
        }

        if ($page !== '') {
            $query = '?page=' . rawurlencode($page);
            if ($this->workspace !== 'published') {
                $query .= '&workspace=' . rawurlencode($this->workspace);
            }
            return '/' . $query;
        }

        return '#';
    }

    private function path(string $menuId): string
    {
        return $this->root . '/storage/workspaces/' . $this->workspace . '/navigation/' . $this->sanitizeMenuId($menuId) . '.json';
    }

    private function sanitizeWorkspace(string $workspace): string
    {
        $workspace = trim($workspace);
        return in_array($workspace, ['draft', 'review', 'published', 'archive'], true) ? $workspace : 'published';
    }

    private function sanitizeMenuId(string $menuId): string
    {
        $menuId = strtolower(trim($menuId));
        $menuId = preg_replace('/[^a-z0-9_-]+/', '-', $menuId) ?: 'main';
        $menuId = trim($menuId, '-_');
        return $menuId !== '' ? $menuId : 'main';
    }
}