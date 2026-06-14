<?php
declare(strict_types=1);

namespace TreeForge\Renderer;

use TreeForge\Core\Config;
use TreeForge\Core\MarkdownRenderer;
use TreeForge\Core\Page;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Twig-basierter Frontend-Renderer.
 *
 * Patch 133 ist bewusst testweise/optional:
 *   /?page=home&workspace=draft&renderer=twig
 *
 * HtmlRenderer bleibt zunächst Legacy/Fallback.
 */
class TwigPageRenderer
{
    protected ?Environment $twig = null;

    /** @var array<int,string> */
    protected array $areaStack = [];

    protected string $collectedCss = '';

    public function __construct(
        protected string $root,
        protected string $workspace = 'published',
        protected string $templateId = 'core'
    ) {
        $this->workspace = $this->cleanWorkspace($workspace);
    }

    public function render(Page $page, Config $config): string
    {
        if (!class_exists(Environment::class)) {
            throw new \RuntimeException('Twig ist noch nicht installiert. Bitte ausführen: composer update twig/twig');
        }

        $pageData = $page->all();
        $layout = $this->layoutFor($pageData);
        $content = $this->renderNodes((array)($pageData['children'] ?? []));

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
            'preview_bar' => $this->previewBarData($pageData),
            'collected_css' => $this->collectedCss,
        ]);
    }

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

        // Draft/Review Preview darf auf Published zurückfallen.
        if (!file_exists($file) && $this->workspace !== 'published') {
            $published = $this->areaFile($id, 'published');
            if (file_exists($published)) {
                $file = $published;
            }
        }

        // Für lokale Tests: Published darf auf Draft fallen, falls noch nicht veröffentlicht.
        if (!file_exists($file) && $this->workspace === 'published') {
            $draft = $this->areaFile($id, 'draft');
            if (file_exists($draft)) {
                $file = $draft;
            }
        }

        if (!file_exists($file)) {
            return '';
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            return '<!-- TreeForge: invalid area json: ' . htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' -->';
        }

        $this->areaStack[] = $id;
        try {
            return $this->renderNodes((array)($data['children'] ?? []));
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
            if (file_exists($file)) {
                $data = json_decode((string)file_get_contents($file), true);
                return is_array($data) && !empty($data['children']);
            }
        }

        return false;
    }

    protected function renderNode(array $node): string
    {
        $type = $this->normalizeNodeType((string)($node['type'] ?? 'unknown'));

        if ($type === 'css') {
            $this->collectCss($node);
            return '';
        }

        $view = $this->nodeView($node, $type);
        $template = $this->nodeTemplate($type);

        return $this->twig()->render($template, ['node' => $view]);
    }

    protected function nodeView(array $node, string $type): array
    {
        $properties = (array)($node['properties'] ?? []);
        $content = (array)($properties['content'] ?? []);
        $layout = (array)($properties['layout'] ?? []);
        $behavior = (array)($properties['behavior'] ?? []);
        $advanced = (array)($properties['advanced'] ?? []);

        $id = (string)($node['id'] ?? 'unknown');
        $view = [
            'id' => $id,
            'type' => $type,
            'original_type' => (string)($node['type'] ?? 'unknown'),
            'title' => (string)($node['title'] ?? $type),
            'dom_id' => $this->domId($node),
            'class' => $this->nodeClass($node, $type),
            'properties' => $properties,
            'children_html' => $this->renderNodes((array)($node['children'] ?? [])),
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
            $settings = (array)($advanced['settings'] ?? []);
            $view['columns'] = max(1, min(12, (int)($settings['columns'] ?? $layout['columns'] ?? 2)));
            $view['gap'] = (string)($settings['gap'] ?? $layout['gap'] ?? '1rem');
        }

        return $view;
    }

    protected function twig(): Environment
    {
        if ($this->twig !== null) {
            return $this->twig;
        }

        $paths = [];

        // Später kommen templates/<active>/ und Parent-Templates davor.
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

    protected function assetCss(): array
    {
        $publisherFile = $this->root . '/app/Core/Templates/TemplateAssetPublisher.php';
        if (!class_exists(\TreeForge\Core\Templates\TemplateAssetPublisher::class) && file_exists($publisherFile)) {
            require_once $publisherFile;
        }

        if (!class_exists(\TreeForge\Core\Templates\TemplateAssetPublisher::class)) {
            return [];
        }

        $publisher = new \TreeForge\Core\Templates\TemplateAssetPublisher($this->root);
        return $publisher->publishCoreCss();
    }

    protected function previewBarData(array $pageData): array
    {
        $workspace = $this->workspace;
        $previewFlag = strtolower(trim((string)($_GET['preview'] ?? '')));
        $enabled = $workspace !== 'published' || in_array($previewFlag, ['1', 'true', 'yes', 'on', 'ja'], true);

        $pageId = $this->cleanPageId((string)($pageData['id'] ?? ($_GET['page'] ?? 'home')));
        if ($pageId === '') {
            $pageId = 'home';
        }

        $title = (string)($pageData['title'] ?? $pageId);
        $renderer = strtolower(trim((string)($_GET['renderer'] ?? getenv('TREEFORGE_RENDERER') ?: 'twig')));
        if ($renderer === '') {
            $renderer = 'twig';
        }

        $baseQuery = 'page=' . rawurlencode($pageId);
        $workspaceQuery = $baseQuery . '&workspace=' . rawurlencode($workspace);

        return [
            'enabled' => $enabled,
            'workspace' => $workspace,
            'page_id' => $pageId,
            'title' => $title,
            'renderer' => $renderer,
            'edit_url' => '/admin/explorer-v2/?' . $workspaceQuery,
            'pages_url' => '/admin/pages/',
            'preview_url' => '/?' . $workspaceQuery,
            'published_url' => '/?' . $baseQuery,
            'strict_url' => '/?' . $workspaceQuery . '&renderer=twig-strict',
            'legacy_url' => '/?' . $workspaceQuery . '&renderer=legacy',
        ];
    }

    protected function cleanPageId(string $id): string
    {
        $id = strtolower(trim($id));
        return preg_replace('/[^a-z0-9_-]/', '', $id) ?: '';
    }
    protected function siteData(Config $config): array
    {
        return [
            'name' => (string)$config->get('name', 'TreeForge CMS'),
            'tagline' => (string)$config->get('tagline', 'Structure first. Content grows.'),
            'logo' => '/assets/brand/treeforge-logo.svg',
        ];
    }

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

    protected function collectCss(array $node): void
    {
        $properties = (array)($node['properties'] ?? []);
        $content = (array)($properties['content'] ?? []);
        $css = trim((string)($content['css'] ?? $content['content'] ?? $node['css'] ?? $node['content'] ?? ''));

        if ($css === '') {
            return;
        }

        $this->collectedCss .= "\n/* CSS Node: " . ((string)($node['id'] ?? 'unknown')) . " */\n" . $css . "\n";
    }

    protected function domId(array $node): string
    {
        $properties = (array)($node['properties'] ?? []);
        $advanced = (array)($properties['advanced'] ?? []);
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
        $properties = (array)($node['properties'] ?? []);
        $advanced = (array)($properties['advanced'] ?? []);
        $customClass = trim((string)($advanced['css_class'] ?? $node['css_class'] ?? ''));
        $customClass = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $customClass) ?: '';
        $customClass = trim(preg_replace('/\s+/', ' ', $customClass) ?: '');

        return trim('tf-node tf-node-' . $type . ($customClass !== '' ? ' ' . $customClass : ''));
    }

    protected function normalizeNodeType(string $type): string
    {
        $key = strtolower(trim($type));
        $key = str_replace(['_node', '-node'], 'node', $key);

        $map = [
            'text' => 'text',
            'textnode' => 'text',
            'heading' => 'heading',
            'headingnode' => 'heading',
            'titlenode' => 'heading',
            'image' => 'image',
            'imagenode' => 'image',
            'button' => 'button',
            'buttonnode' => 'button',
            'markdown' => 'markdown',
            'markdownnode' => 'markdown',
            'html' => 'html',
            'htmlnode' => 'html',
            'css' => 'css',
            'cssnode' => 'css',
            'codeblock' => 'codeblock',
            'codeblocknode' => 'codeblock',
            'code' => 'codeblock',
            'columns' => 'columns',
            'columnsnode' => 'columns',
            'column' => 'column',
            'columnnode' => 'column',
            'rootnode' => 'root',
            'page' => 'root',
        ];

        return $map[$key] ?? 'unknown';
    }

    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'on', 'ja'], true);
    }

    protected function areaFile(string $id, string $workspace): string
    {
        return $this->root . '/storage/workspaces/' . $workspace . '/areas/' . $id . '.json';
    }

    protected function cleanAreaId(string $id): string
    {
        $id = strtolower(trim($id));
        return preg_replace('/[^a-z0-9_-]/', '', $id) ?: '';
    }

    protected function cleanWorkspace(string $workspace): string
    {
        $workspace = strtolower(trim($workspace));
        return in_array($workspace, ['published', 'draft', 'review'], true) ? $workspace : 'published';
    }
}