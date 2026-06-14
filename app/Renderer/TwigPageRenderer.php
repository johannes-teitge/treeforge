<?php
declare(strict_types=1);

namespace TreeForge\Renderer;

use League\CommonMark\CommonMarkConverter;
use TreeForge\Core\Config;
use TreeForge\Core\Icons\IconRenderer;
use TreeForge\Core\MarkdownRenderer;
use TreeForge\Core\Navigation\NavigationManager;
use TreeForge\Core\Page;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class TwigPageRenderer
{
    protected ?Environment $twig = null;

    /** @var array<int,string> */
    protected array $areaStack = [];

    protected string $collectedCss = '';
    protected ?IconRenderer $iconRenderer = null;
    protected string $currentPageId = 'home';

    /** @var array<int,mixed> */
    protected array $currentPageChildren = [];

    public function __construct(
        protected string $root,
        protected string $workspace = 'published',
        protected string $templateId = 'core'
    ) {
        $this->root = rtrim($this->root, '/\\');
        $this->workspace = $this->cleanWorkspace($this->workspace);
    }

    public function render(Page $page, Config $config): string
    {
        if (!class_exists(Environment::class)) {
            throw new \RuntimeException('Twig ist nicht installiert. Bitte ausführen: composer require twig/twig:^3.0');
        }

        $this->collectedCss = '';
        $pageData = $page->all();
        $this->currentPageId = (string)($pageData['id'] ?? ($_GET['page'] ?? 'home'));
        $this->currentPageChildren = is_array($pageData['children'] ?? null) ? $pageData['children'] : [];
        $layout = $this->layoutFor($pageData);
        $content = $this->renderNodes($this->currentPageChildren);

        return $this->twig()->render($layout, [
            'site' => $this->siteData($config),
            'page' => $this->pageData($pageData),
            'template' => [
                'id' => $this->templateId,
                'name' => 'TreeForge Core',
            ],
            'assets' => [
                'css' => $this->assetCss(),
                'js' => [],
            ],
            'content' => $content,
            'workspace' => $this->workspace,
            'preview_bar' => $this->previewBarData(),
            'collected_css' => $this->collectedCss,
        ]);
    }

    /** @param array<int,mixed>|null $nodes */
    public function renderNodes(?array $nodes = []): string
    {
        $nodes ??= [];
        $html = '';

        foreach ($nodes as $node) {
            if (is_array($node)) {
                $html .= $this->renderNode($node) . "\n";
            }
        }

        return trim($html);
    }

    public function renderArea(string $id): string
    {
        $id = $this->cleanAreaId($id);
        if ($id === '') {
            return '';
        }

        if (in_array($id, $this->areaStack, true)) {
            return '<!-- TreeForge: area recursion prevented: ' . htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' -->';
        }

        $file = $this->areaFile($id, $this->workspace);

        if (!is_file($file) && $this->workspace !== 'published') {
            $published = $this->areaFile($id, 'published');
            if (is_file($published)) {
                $file = $published;
            }
        }

        if (!is_file($file) && $this->workspace === 'published') {
            $draft = $this->areaFile($id, 'draft');
            if (is_file($draft)) {
                $file = $draft;
            }
        }

        if (!is_file($file)) {
            return '';
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            return '<!-- TreeForge: invalid area json: ' . htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' -->';
        }

        $this->areaStack[] = $id;
        try {
            return $this->renderNodes(is_array($data['children'] ?? null) ? $data['children'] : []);
        } finally {
            array_pop($this->areaStack);
        }
    }

    public function hasArea(string $id): bool
    {
        $id = $this->cleanAreaId($id);
        if ($id === '') {
            return false;
        }

        foreach (array_unique([$this->workspace, 'published', 'draft']) as $workspace) {
            $file = $this->areaFile($id, $workspace);
            if (!is_file($file)) {
                continue;
            }

            $data = json_decode((string)file_get_contents($file), true);
            return is_array($data) && !empty($data['children']);
        }

        return false;
    }

    public function renderMenu(string $menuId): string
    {
        return $this->navigationManager()->render($menuId, $this->currentPageId);
    }

    public function hasMenu(string $menuId): bool
    {
        return $this->navigationManager()->exists($menuId);
    }

    protected function renderNode(array $node): string
    {
        $type = $this->normalizeNodeType((string)($node['type'] ?? 'unknown'));

        if ($type === 'css') {
            $this->collectCss($node);
            return '';
        }

        // TF_PATCH_147_NODE_CSS_COLLECTOR
        $this->collectNodePropertyCss($node, $type);

        $view = $this->nodeView($node, $type);
        $template = $this->nodeTemplate($type);

        return $this->twig()->render($template, ['node' => $view]);
    }

    /** @return array<string,mixed> */
    protected function nodeView(array $node, string $type): array
    {
        $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];
        $content = is_array($properties['content'] ?? null) ? $properties['content'] : [];
        $layout = is_array($properties['layout'] ?? null) ? $properties['layout'] : [];
        $behavior = is_array($properties['behavior'] ?? null) ? $properties['behavior'] : [];
        $advanced = is_array($properties['advanced'] ?? null) ? $properties['advanced'] : [];

        $id = (string)($node['id'] ?? 'unknown');
        $view = [
            'id' => $id,
            'type' => $type,
            'original_type' => (string)($node['type'] ?? 'unknown'),
            'title' => (string)($node['title'] ?? $type),
            'dom_id' => $this->domId($node),
            'class' => $this->nodeClass($node, $type),
            'properties' => $properties,
            'children_html' => $this->renderNodes(is_array($node['children'] ?? null) ? $node['children'] : []),
        ];

        if ($type === 'heading') {
            $level = strtolower((string)($content['level'] ?? $node['level'] ?? 'h2'));
            if (!in_array($level, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                $level = 'h2';
            }
            $view['level'] = $level;
            $view['text'] = (string)($content['text'] ?? $content['content'] ?? $node['content'] ?? $node['title'] ?? '');
        }

        if ($type === 'text') {
            $text = (string)($content['content'] ?? $content['text'] ?? $node['content'] ?? '');
            $view['content'] = $text;
            $view['content_html'] = $this->renderTextContentAsParagraphs($text);
        }

        if ($type === 'image') {
            $view['src'] = (string)($content['src'] ?? $node['src'] ?? '');
            $view['alt'] = (string)($content['alt'] ?? $node['alt'] ?? '');
            $view['caption'] = (string)($content['caption'] ?? $node['caption'] ?? '');
        }

        if ($type === 'button') {
            $view['label'] = (string)($content['label'] ?? $node['label'] ?? 'Button');
            $view['url'] = (string)($behavior['url'] ?? $content['url'] ?? $node['url'] ?? '#');
            $view['target'] = (string)($behavior['target'] ?? $content['target'] ?? '');
            $view['variant'] = (string)($advanced['variant'] ?? $content['variant'] ?? 'primary');
        }

        if ($type === 'markdown') {
            $markdown = (string)($content['markdown'] ?? $content['content'] ?? $node['content'] ?? '');
            $view['markdown'] = $markdown;
            $view['html'] = MarkdownRenderer::toHtml($markdown);
        }

        if ($type === 'html') {
            $view['html'] = (string)($content['html'] ?? $content['content'] ?? $node['html'] ?? $node['content'] ?? '');
        }

        if ($type === 'codeblock') {
            $language = strtolower((string)($content['language'] ?? $node['language'] ?? 'text'));
            $view['language'] = preg_replace('/[^a-z0-9_+-]/', '', $language) ?: 'text';
            $view['code'] = (string)($content['code'] ?? $content['content'] ?? $node['code'] ?? '');
            $view['caption'] = (string)($content['caption'] ?? $node['caption'] ?? '');
            $view['show_line_numbers'] = $this->truthy($content['show_line_numbers'] ?? $node['show_line_numbers'] ?? false);
            $view['wrap'] = $this->truthy($content['wrap'] ?? $node['wrap'] ?? false);
        }

        if ($type === 'columns') {
            $settings = is_array($advanced['settings'] ?? null) ? $advanced['settings'] : [];
            $view['columns'] = max(1, min(12, (int)($settings['columns'] ?? $layout['columns'] ?? 2)));
            $view['gap'] = (string)($settings['gap'] ?? $layout['gap'] ?? '1rem');
        }

        if ($type === 'menuitem') {
            $view['label'] = (string)($content['label'] ?? $node['label'] ?? $node['title'] ?? 'Menüpunkt');
            $view['href'] = $this->normalizeHref((string)($content['href'] ?? $behavior['url'] ?? $node['href'] ?? '#'));
            $view['target'] = $this->normalizeTarget((string)($content['target'] ?? $behavior['target'] ?? $node['target'] ?? '_self'));
            $view['description'] = (string)($content['description'] ?? $node['description'] ?? '');
            $view['icon'] = (string)($content['icon'] ?? $node['icon'] ?? '');
            $view['badge'] = (string)($content['badge'] ?? $node['badge'] ?? '');
            $view['rel'] = $this->normalizeRel((string)($content['rel'] ?? $node['rel'] ?? ''));
            $view['aria_label'] = (string)($content['aria_label'] ?? $node['aria_label'] ?? '');
            $view['item_type'] = (string)($content['item_type'] ?? $node['item_type'] ?? 'link');
        }

        if ($type === 'pagemenu') {
            $mode = strtolower(trim((string)($content['mode'] ?? 'manual')));
            if (!in_array($mode, ['manual', 'headings', 'hybrid'], true)) {
                $mode = 'manual';
            }

            $variant = strtolower(trim((string)($content['variant'] ?? 'vertical')));
            $variant = preg_replace('/[^a-z0-9_-]/', '', $variant) ?: 'vertical';
            $variantAliases = [
                'sidebar' => 'vertical',
                'button' => 'buttons',
                'buttonbar' => 'buttons',
                'source' => 'sources',
                'references' => 'sources',
            ];
            $variant = $variantAliases[$variant] ?? $variant;
            if (!in_array($variant, ['vertical', 'horizontal', 'buttons', 'pills', 'sources', 'compact'], true)) {
                $variant = 'vertical';
            }

            $behaviorValue = strtolower(trim((string)($content['behavior'] ?? 'static')));
            if ($this->truthy($content['sticky'] ?? false)) {
                $behaviorValue = 'sticky';
            }
            if (!in_array($behaviorValue, ['static', 'sticky', 'popup', 'dropdown'], true)) {
                $behaviorValue = 'static';
            }

            $view['mode'] = $mode;
            $view['variant'] = $variant;
            $view['behavior'] = $behaviorValue;
            $view['menu_title'] = (string)($content['title'] ?? $node['title'] ?? 'Auf dieser Seite');
            $view['show_title'] = $this->truthy($content['show_title'] ?? true);
            $view['sticky'] = $behaviorValue === 'sticky';
            $view['button_label'] = trim((string)($content['button_label'] ?? 'Menü öffnen')) ?: 'Menü öffnen';
            $view['button_icon'] = trim((string)($content['button_icon'] ?? '☰'));
            $view['active_mode'] = strtolower(trim((string)($content['active_mode'] ?? 'none')));
            $view['empty_message'] = trim((string)($content['empty_message'] ?? 'Keine Menüpunkte.')) ?: 'Keine Menüpunkte.';
            $view['items'] = $this->buildPageMenuItems($node, $content, $mode);
        }

        if ($type === 'unknown') {
            $view['message'] = 'Unbekannter Node-Typ: ' . (string)($node['type'] ?? 'unknown');
        }

        return $view;
    }

    protected function twig(): Environment
    {
        if ($this->twig !== null) {
            return $this->twig;
        }

        $paths = [];
        if (is_dir($this->root . '/core/templates')) {
            $paths[] = $this->root . '/core/templates';
        }

        $loader = new FilesystemLoader($paths);
        $this->twig = new Environment($loader, [
            'autoescape' => 'html',
            'cache' => false,
            'strict_variables' => false,
        ]);

        $this->twig->addFunction(new TwigFunction('tf_area', function (string $id): string {
            return $this->renderArea($id);
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new TwigFunction('tf_has_area', function (string $id): bool {
            return $this->hasArea($id);
        }));

        $this->twig->addFunction(new TwigFunction('tf_menu', function (string $menuId = 'main'): string {
            return $this->renderMenu($menuId);
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new TwigFunction('tf_icon', function (string $icon = '', string $label = ''): string {
            return $this->iconRenderer()->render($icon, $label);
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new TwigFunction('tf_has_menu', function (string $menuId = 'main'): bool {
            return $this->hasMenu($menuId);
        }));

        $this->twig->addFunction(new TwigFunction('render_nodes', function ($nodes = []): string {
            return $this->renderNodes(is_array($nodes) ? $nodes : []);
        }, ['is_safe' => ['html']]));

        return $this->twig;
    }

    protected function nodeTemplate(string $type): string
    {
        $template = 'nodes/' . $type . '.twig';
        if ($this->twig()->getLoader()->exists($template)) {
            return $template;
        }

        return 'nodes/unknown.twig';
    }

    protected function layoutFor(array $pageData): string
    {
        $template = $pageData['template'] ?? [];
        $layout = 'page';

        if (is_array($template)) {
            $layout = (string)($template['layout'] ?? 'page');
        } elseif (isset($pageData['layout'])) {
            $layout = (string)$pageData['layout'];
        }

        $layout = preg_replace('/[^a-z0-9_-]/', '', strtolower($layout)) ?: 'page';
        $file = 'layouts/' . $layout . '.twig';

        return $this->twig()->getLoader()->exists($file) ? $file : 'layouts/page.twig';
    }

    protected function iconRenderer(): IconRenderer
    {
        $file = $this->root . '/app/Core/Icons/IconRenderer.php';
        if (!class_exists(IconRenderer::class) && is_file($file)) {
            require_once $file;
        }

        if ($this->iconRenderer === null) {
            $this->iconRenderer = new IconRenderer($this->root);
        }

        return $this->iconRenderer;
    }

    /** @param array<int,string> $css */
    protected function withIconCss(array $css): array
    {
        foreach ($this->iconRenderer()->cssUrls() as $url) {
            if (!in_array($url, $css, true)) {
                $css[] = $url;
            }
        }

        return $css;
    }
    /** @return array<int,string> */
    protected function assetCss(): array
    {
        $publisherFile = $this->root . '/app/Core/Templates/TemplateAssetPublisher.php';
        if (!class_exists(\TreeForge\Core\Templates\TemplateAssetPublisher::class) && is_file($publisherFile)) {
            require_once $publisherFile;
        }

        if (class_exists(\TreeForge\Core\Templates\TemplateAssetPublisher::class)) {
            $publisher = new \TreeForge\Core\Templates\TemplateAssetPublisher($this->root);
            if (method_exists($publisher, 'publishCoreCss')) {
                return $this->withIconCss($publisher->publishCoreCss());
            }
            if (method_exists($publisher, 'publishCoreAssets')) {
                $publisher->publishCoreAssets();
            }
        }

        if (is_file($this->root . '/public/assets/treeforge/core/css/core-template.css')) {
            $hash = substr(sha1_file($this->root . '/public/assets/treeforge/core/css/core-template.css') ?: '1', 0, 12);
            return ['/assets/treeforge/core/css/core-template.css?v=' . $hash];
        }

        if (is_file($this->root . '/public/assets/css/treeforge-core-template.css')) {
            return $this->withIconCss(['/assets/css/treeforge-core-template.css']);
        }

        return $this->withIconCss([]);
    }

    /** @return array<string,string> */
    protected function siteData(Config $config): array
    {
        return [
            'name' => (string)$config->get('name', 'TreeForge CMS'),
            'tagline' => (string)$config->get('tagline', 'Structure first. Content grows with Layers.'),
            'logo' => '/assets/brand/treeforge-logo.svg',
            'icon' => '/assets/brand/treeforge-icon.svg',
        ];
    }

    /** @return array<string,mixed> */
    protected function pageData(array $pageData): array
    {
        $id = (string)($pageData['id'] ?? 'home');
        return [
            'id' => $id,
            'title' => (string)($pageData['title'] ?? $id),
            'slug' => (string)($pageData['slug'] ?? $id),
            'lang' => (string)($pageData['lang'] ?? $pageData['language'] ?? 'de'),
            'body_class' => 'tf-page-' . preg_replace('/[^a-z0-9_-]/', '-', strtolower($id)),
            'generated_css' => '',
            'children' => is_array($pageData['children'] ?? null) ? $pageData['children'] : [],
        ];
    }

    /** @return array<string,mixed> */
    protected function previewBarData(): array
    {
        $renderer = strtolower(trim((string)($_GET['renderer'] ?? 'twig')));
        $page = $this->currentPageId !== '' ? $this->currentPageId : 'home';
        $enabled = $this->workspace !== 'published' || (string)($_GET['preview'] ?? '') === '1';

        return [
            'enabled' => $enabled,
            'workspace' => $this->workspace,
            'page_id' => $page,
            'renderer' => $renderer === '' ? 'twig' : $renderer,
            'edit_url' => '/admin/explorer-v2/?page=' . rawurlencode($page) . '&workspace=' . rawurlencode($this->workspace),
            'pages_url' => '/admin/pages/',
            'published_url' => '/?page=' . rawurlencode($page),
            'strict_url' => '/?page=' . rawurlencode($page) . '&workspace=' . rawurlencode($this->workspace) . '&renderer=twig-strict',
            'legacy_url' => '/?page=' . rawurlencode($page) . '&workspace=' . rawurlencode($this->workspace) . '&renderer=legacy',
        ];
    }

    protected function renderTextContentAsParagraphs(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return '';
        }

        $blocks = preg_split('/\n{2,}/', $text) ?: [];
        $paragraphs = [];

        foreach ($blocks as $block) {
            $block = trim($block, "\n");
            if (trim($block) === '') {
                continue;
            }

            $escaped = htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escaped = preg_replace('/\n/', "<br>\n", $escaped) ?? $escaped;
            $paragraphs[] = '<p>' . $escaped . '</p>';
        }

        return implode("\n  ", $paragraphs);
    }


    /**
     * Sammelt CSS aus den Standard-Property-Gruppen einer Node.
     *
     * Unterstützte Gruppen:
     * - properties.layout: display, alignment, width, max_width, min_height, columns, gap
     * - properties.spacing: margin, padding, gap
     * - properties.design: background, color, border, border_radius, box_shadow, style
     * - properties.advanced: custom_style
     * - properties.custom_css: scoped Custom CSS
     */
    protected function collectNodePropertyCss(array $node, string $type): void
    {
        $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];
        $layout = is_array($properties['layout'] ?? null) ? $properties['layout'] : [];
        $spacing = is_array($properties['spacing'] ?? null) ? $properties['spacing'] : [];
        $design = is_array($properties['design'] ?? null) ? $properties['design'] : [];
        $advanced = is_array($properties['advanced'] ?? null) ? $properties['advanced'] : [];

        $selector = '#' . $this->domId($node);
        $declarations = [];

        $add = function (string $property, mixed $value) use (&$declarations): void {
            $property = trim($property);
            $value = $this->safeCssValue($value);

            if ($property === '' || $value === '') {
                return;
            }

            $declarations[$property] = $value;
        };

        $display = strtolower(trim((string)($layout['display'] ?? '')));
        if (in_array($display, ['block', 'inline-block', 'flex', 'grid', 'none'], true)) {
            $add('display', $display);
        }

        $alignment = strtolower(trim((string)($layout['alignment'] ?? $layout['text_align'] ?? '')));
        if (in_array($alignment, ['left', 'center', 'right', 'justify'], true)) {
            $add('text-align', $alignment);
        }

        $add('width', $layout['width'] ?? '');
        $add('max-width', $layout['max_width'] ?? $layout['max-width'] ?? '');
        $add('min-width', $layout['min_width'] ?? $layout['min-width'] ?? '');
        $add('min-height', $layout['min_height'] ?? $layout['min-height'] ?? '');

        $add('margin', $spacing['margin'] ?? '');
        $add('padding', $spacing['padding'] ?? '');
        $add('gap', $spacing['gap'] ?? $layout['gap'] ?? '');

        $add('background', $design['background'] ?? '');
        $add('color', $design['color'] ?? '');
        $add('border', $design['border'] ?? '');
        $add('border-radius', $design['border_radius'] ?? $design['border-radius'] ?? '');
        $add('box-shadow', $design['box_shadow'] ?? $design['box-shadow'] ?? '');

        if ($type === 'columns') {
            $settings = is_array($advanced['settings'] ?? null) ? $advanced['settings'] : [];
            $columns = max(1, min(12, (int)($settings['columns'] ?? $layout['columns'] ?? 2)));
            $gap = (string)($settings['gap'] ?? $layout['gap'] ?? $spacing['gap'] ?? '1rem');

            $add('--tf-columns', (string)$columns);
            $add('--tf-column-gap', $gap);
        }

        $customStyle = trim((string)($advanced['custom_style'] ?? $design['style'] ?? ''));
        if ($customStyle !== '') {
            foreach ($this->parseCssDeclarations($customStyle) as $property => $value) {
                $add($property, $value);
            }
        }

        if ($declarations !== []) {
            $this->collectedCss .= "\n/* Node properties: " . ((string)($node['id'] ?? 'unknown')) . " */\n";
            $this->collectedCss .= $selector . " {\n";
            foreach ($declarations as $property => $value) {
                $this->collectedCss .= '  ' . $property . ': ' . $value . ";\n";
            }
            $this->collectedCss .= "}\n";
        }

        $customCss = trim((string)($properties['custom_css'] ?? $node['custom_css'] ?? ''));
        if ($customCss !== '') {
            $scoped = $this->scopeCustomCss($customCss, $selector);
            if ($scoped !== '') {
                $this->collectedCss .= "\n/* Node custom CSS: " . ((string)($node['id'] ?? 'unknown')) . " */\n" . $scoped . "\n";
            }
        }
    }

    /** @return array<string,string> */
    protected function parseCssDeclarations(string $css): array
    {
        $css = trim($css);
        if ($css === '' || str_contains($css, '{') || str_contains($css, '}')) {
            return [];
        }

        $declarations = [];
        foreach (explode(';', $css) as $part) {
            $part = trim($part);
            if ($part === '' || !str_contains($part, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $part, 2));
            $property = strtolower(preg_replace('/[^a-z0-9_-]+/', '-', $property) ?? '');
            $value = $this->safeCssValue($value);

            if ($property !== '' && $value !== '') {
                $declarations[$property] = $value;
            }
        }

        return $declarations;
    }

    protected function scopeCustomCss(string $css, string $selector): string
    {
        $css = trim(str_replace(["\r\n", "\r"], "\n", $css));
        if ($css === '' || !$this->isSafeCssBlock($css)) {
            return '';
        }

        // Einfache Eingabe wie "color:red; font-weight:bold" als Deklarationen behandeln.
        if (!str_contains($css, '{') && !str_contains($css, '}')) {
            $declarations = $this->parseCssDeclarations($css);
            if ($declarations === []) {
                return '';
            }

            $out = $selector . " {\n";
            foreach ($declarations as $property => $value) {
                $out .= '  ' . $property . ': ' . $value . ";\n";
            }
            return $out . '}';
        }

        // Komfortsyntax: & strong { ... } oder @media (...) { & { ... } }
        if (str_contains($css, '&')) {
            return str_replace('&', $selector, $css);
        }

        // Wenn der Nutzer eigene Selektoren schreibt, werden sie automatisch unter die Node gescoped.
        return preg_replace_callback(
            '/(^|})(\s*)([^@{}][^{]+)\{/',
            function (array $match) use ($selector): string {
                $prefix = $match[1];
                $space = $match[2];
                $rawSelector = trim($match[3]);

                if ($rawSelector === '') {
                    return $match[0];
                }

                return $prefix . $space . $selector . ' ' . $rawSelector . ' {';
            },
            $css
        ) ?? '';
    }

    protected function safeCssValue(mixed $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(["\0", "\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $lower = strtolower($value);

        if (str_contains($value, '{') || str_contains($value, '}') || str_contains($value, '<') || str_contains($value, '>')) {
            return '';
        }

        if (str_contains($lower, 'javascript:') || str_contains($lower, 'expression(') || str_contains($lower, '</style')) {
            return '';
        }

        return trim($value, " ;");
    }

    protected function isSafeCssBlock(string $css): bool
    {
        $lower = strtolower($css);

        if (str_contains($lower, '</style') || str_contains($lower, '<script') || str_contains($lower, 'javascript:') || str_contains($lower, 'expression(')) {
            return false;
        }

        return true;
    }

    protected function collectCss(array $node): void
    {
        $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];
        $content = is_array($properties['content'] ?? null) ? $properties['content'] : [];
        $css = trim((string)($content['css'] ?? $content['content'] ?? $node['css'] ?? $node['content'] ?? ''));

        if ($css === '') {
            return;
        }

        $this->collectedCss .= "\n/* CSS Node: " . ((string)($node['id'] ?? 'unknown')) . " */\n" . $css . "\n";
    }

    protected function domId(array $node): string
    {
        $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];
        $advanced = is_array($properties['advanced'] ?? null) ? $properties['advanced'] : [];
        $customId = trim((string)($advanced['css_id'] ?? $node['css_id'] ?? ''));

        if ($customId !== '') {
            return preg_replace('/[^A-Za-z0-9_-]/', '-', $customId) ?: 'tf-node';
        }

        $id = trim((string)($node['id'] ?? 'unknown')) ?: 'unknown';
        $id = str_replace('_', '-', $id);
        $id = preg_replace('/[^A-Za-z0-9_-]/', '-', $id) ?: 'unknown';

        return 'tf-' . $id;
    }

    protected function nodeClass(array $node, string $type): string
    {
        $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];
        $advanced = is_array($properties['advanced'] ?? null) ? $properties['advanced'] : [];
        $custom = trim((string)($advanced['css_class'] ?? $node['css_class'] ?? ''));

        $classes = ['tf-node', 'tf-node-' . $type];
        if ($custom !== '') {
            foreach (preg_split('/\s+/', $custom) ?: [] as $class) {
                $class = preg_replace('/[^A-Za-z0-9_-]/', '-', $class) ?: '';
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }

        return implode(' ', array_unique($classes));
    }

    protected function normalizeNodeType(string $type): string
    {
        $key = strtolower(trim($type));
        $key = preg_replace('/[^a-z0-9_-]+/', '', $key) ?: 'unknown';

        return match ($key) {
            'textnode' => 'text',
            'headingnode', 'titlenode', 'title' => 'heading',
            'imagenode' => 'image',
            'buttonnode' => 'button',
            'markdownnode' => 'markdown',
            'cssnode' => 'css',
            'htmlnode' => 'html',
            'javascriptnode', 'jsnode', 'scriptnode' => 'javascript',
            'codeblocknode', 'codenode', 'code' => 'codeblock',
            'pagemenunode', 'linkmenunode', 'localmenu', 'anchor_menu', 'pagemenu' => 'pagemenu',
            'menuitemnode', 'linkitemnode', 'menu_item', 'menuitem' => 'menuitem',
            'columnsnode' => 'columns',
            'columnnode' => 'column',
            default => $key,
        };
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,mixed> $content
     * @return array<int,array<string,mixed>>
     */
    protected function buildPageMenuItems(array $node, array $content, string $mode): array
    {
        $manual = $this->manualMenuItemsFromChildren(is_array($node['children'] ?? null) ? $node['children'] : []);

        if ($mode === 'manual') {
            return $manual;
        }

        $headingItems = $this->headingMenuItems($content);

        if ($mode === 'headings') {
            return $headingItems;
        }

        $manualPosition = strtolower(trim((string)($content['manual_position'] ?? 'after')));
        return $manualPosition === 'before'
            ? array_values(array_merge($manual, $headingItems))
            : array_values(array_merge($headingItems, $manual));
    }

    /**
     * @param array<int,mixed> $children
     * @return array<int,array<string,mixed>>
     */
    protected function manualMenuItemsFromChildren(array $children): array
    {
        $items = [];

        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $type = $this->normalizeNodeType((string)($child['type'] ?? ''));
            if ($type !== 'menuitem') {
                continue;
            }

            $properties = is_array($child['properties'] ?? null) ? $child['properties'] : [];
            $content = is_array($properties['content'] ?? null) ? $properties['content'] : [];
            $behavior = is_array($properties['behavior'] ?? null) ? $properties['behavior'] : [];

            $label = trim((string)($content['label'] ?? $child['label'] ?? $child['title'] ?? 'Menüpunkt'));
            $href = $this->normalizeHref((string)($content['href'] ?? $behavior['url'] ?? $child['href'] ?? '#'));

            if ($label === '' || $href === '') {
                continue;
            }

            $items[] = [
                'type' => 'manual',
                'id' => (string)($child['id'] ?? ''),
                'label' => $label,
                'href' => $href,
                'target' => $this->normalizeTarget((string)($content['target'] ?? $behavior['target'] ?? $child['target'] ?? '_self')),
                'description' => trim((string)($content['description'] ?? $child['description'] ?? '')),
                'icon' => trim((string)($content['icon'] ?? $child['icon'] ?? '')),
                'badge' => trim((string)($content['badge'] ?? $child['badge'] ?? '')),
                'rel' => $this->normalizeRel((string)($content['rel'] ?? $child['rel'] ?? '')),
                'aria_label' => trim((string)($content['aria_label'] ?? $child['aria_label'] ?? '')),
                'item_type' => trim((string)($content['item_type'] ?? $child['item_type'] ?? 'link')) ?: 'link',
                'level' => 1,
            ];
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $content
     * @return array<int,array<string,mixed>>
     */
    protected function headingMenuItems(array $content): array
    {
        $levels = $this->normalizeStringList($content['heading_levels'] ?? ['h2', 'h3']);
        if ($levels === []) {
            $levels = ['h2', 'h3'];
        }

        $levels = array_values(array_intersect(array_map('strtolower', $levels), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']));
        $excludeIds = $this->normalizeStringList($content['exclude_heading_ids'] ?? []);
        $items = [];

        $this->collectHeadingItems($this->currentPageChildren, $items, $levels, $excludeIds);

        return $items;
    }

    /**
     * @param array<int,mixed> $nodes
     * @param array<int,array<string,mixed>> $items
     * @param array<int,string> $levels
     * @param array<int,string> $excludeIds
     */
    protected function collectHeadingItems(array $nodes, array &$items, array $levels, array $excludeIds): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $type = $this->normalizeNodeType((string)($node['type'] ?? ''));

            if ($type === 'heading') {
                $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];
                $content = is_array($properties['content'] ?? null) ? $properties['content'] : [];
                $navigation = is_array($properties['navigation'] ?? null) ? $properties['navigation'] : [];
                $advanced = is_array($properties['advanced'] ?? null) ? $properties['advanced'] : [];
                $id = (string)($node['id'] ?? '');
                $level = strtolower((string)($content['level'] ?? $node['level'] ?? 'h2'));

                if (in_array($id, $excludeIds, true) || !in_array($level, $levels, true)) {
                    // Überschrift bewusst übersprungen.
                } elseif (!$this->headingAllowedInPageMenu($node, $content, $navigation, $advanced)) {
                    // Überschrift schließt sich selbst aus.
                } else {
                    $label = trim((string)($navigation['menu_label'] ?? $content['menu_label'] ?? $content['text'] ?? $content['content'] ?? $node['title'] ?? ''));
                    if ($label !== '') {
                        $items[] = [
                            'type' => 'heading',
                            'id' => $id,
                            'label' => $label,
                            'href' => '#' . $this->domId($node),
                            'target' => '_self',
                            'description' => '',
                            'level' => max(1, (int)substr($level, 1) - 1),
                        ];
                    }
                }
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $this->collectHeadingItems($node['children'], $items, $levels, $excludeIds);
            }
        }
    }

    /** @param array<string,mixed> $node @param array<string,mixed> $content @param array<string,mixed> $navigation @param array<string,mixed> $advanced */
    protected function headingAllowedInPageMenu(array $node, array $content, array $navigation, array $advanced): bool
    {
        foreach (['include_in_page_menu', 'show_in_page_menu', 'in_page_menu'] as $key) {
            if (array_key_exists($key, $navigation)) {
                return $this->truthy($navigation[$key]);
            }
            if (array_key_exists($key, $content)) {
                return $this->truthy($content[$key]);
            }
        }

        $classes = (string)($advanced['css_class'] ?? $node['css_class'] ?? '');
        if (preg_match('/(^|\s)(no-menu|no-pagemenu|no-toc)(\s|$)/i', $classes)) {
            return false;
        }

        return true;
    }

    /** @return array<int,string> */
    protected function normalizeStringList(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[,\n;]+/', (string)$value) ?: [];
        }

        $result = [];
        foreach ($items as $item) {
            $item = strtolower(trim((string)$item));
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    protected function normalizeHref(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '#';
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/') || preg_match('#^https?://#i', $href) || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return $href;
        }

        return '#' . ltrim($href, '#');
    }

    protected function normalizeRel(string $rel): string
    {
        $parts = preg_split('/[\s,]+/', strtolower(trim($rel))) ?: [];
        $allowed = ['noopener', 'noreferrer', 'nofollow', 'sponsored', 'ugc'];
        $result = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && in_array($part, $allowed, true)) {
                $result[] = $part;
            }
        }

        return implode(' ', array_values(array_unique($result)));
    }
    protected function normalizeTarget(string $target): string
    {
        $target = trim($target);
        return in_array($target, ['_self', '_blank', '_parent', '_top'], true) ? $target : '_self';
    }
    protected function navigationManager(): NavigationManager
    {
        $file = $this->root . '/app/Core/Navigation/NavigationManager.php';
        if (!class_exists(NavigationManager::class) && is_file($file)) {
            require_once $file;
        }

        return new NavigationManager($this->root, $this->workspace);
    }

    protected function areaFile(string $id, string $workspace): string
    {
        return $this->root . '/storage/workspaces/' . $this->cleanWorkspace($workspace) . '/areas/' . $id . '.json';
    }

    protected function cleanWorkspace(string $workspace): string
    {
        $workspace = trim($workspace);
        return in_array($workspace, ['draft', 'review', 'published', 'archive'], true) ? $workspace : 'published';
    }

    protected function cleanAreaId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id) ?: '';
        return trim($id, '-_');
    }

    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'on', 'ja'], true);
    }
}