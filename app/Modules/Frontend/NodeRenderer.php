<?php
declare(strict_types=1);

namespace TreeForge\Modules\Frontend;

class NodeRenderer
{
    public function __construct(protected string $root) {}

    public function render(array $node): string
    {
        if (!$this->isRenderable($node)) return '';

        return match ($this->type($node)) {
            'RootNode' => $this->children($node),
            'ContainerNode' => $this->container($node, 'tf-container'),
            'ScheduleContainerNode' => $this->scheduleActive($node) ? $this->container($node, 'tf-container tf-schedule-container') : '',
            'ColumnsNode' => $this->columns($node),
            'ColumnNode' => $this->container($node, 'tf-column'),
            'TextNode' => $this->text($node),
            'MarkdownNode' => $this->markdown($node),
            'HtmlNode' => $this->html($node),
            'CssNode' => '',
            'ImageNode' => $this->image($node),
            'ButtonNode' => $this->button($node),
            'ReferenceNode' => $this->reference($node),
            default => '<div class="tf-unknown-node">Unbekannte Node: ' . $this->e((string)($node['type'] ?? 'Node')) . '</div>',
        };
    }

    protected function children(array $node): string
    {
        $html = '';
        /* PATCH 106 CHILDREN FALLBACK */
        $children = $node['children'] ?? $node['nodes'] ?? $node['content'] ?? [];

        foreach ((array)$children as $child) {
            if (is_array($child)) $html .= $this->render($child);
        }
        return $html;
    }

    protected function container(array $node, string $class): string
    {
        return '<div ' . $this->attrs($node, $class) . '>' . $this->children($node) . '</div>';
    }

    protected function columns(array $node): string
    {
        $p = $this->props($node);
        $count = (int)($p['columns'] ?? $p['layout']['columns'] ?? $node['columns'] ?? count((array)($node['children'] ?? [])) ?: 2);
        $gap = (string)($p['gap'] ?? $p['spacing']['gap'] ?? $node['gap'] ?? '1rem');
        return '<div ' . $this->attrs($node, 'tf-columns', '--tf-columns:' . max(1, $count) . ';--tf-gap:' . $this->css($gap) . ';') . '>' . $this->children($node) . '</div>';
    }

    protected function text(array $node): string
    {
        $p = $this->props($node);
        $text = (string)($p['content']['text'] ?? $p['text'] ?? $p['content'] ?? $node['text'] ?? $node['content'] ?? '');
        return $text === '' ? '' : '<div ' . $this->attrs($node, 'tf-text') . '>' . nl2br($this->e($text)) . '</div>';
    }

    protected function markdown(array $node): string
    {
        $p = $this->props($node);
        $md = (string)($p['content']['markdown'] ?? $p['markdown'] ?? $p['content'] ?? $node['markdown'] ?? $node['content'] ?? '');
        if ($md === '') return '';
        $html = '';
        foreach (preg_split('/\R{2,}/', trim($md)) ?: [] as $paragraph) {
            $html .= '<p>' . nl2br($this->e(trim($paragraph))) . '</p>';
        }
        return '<div ' . $this->attrs($node, 'tf-markdown') . '>' . $html . '</div>';
    }

    protected function html(array $node): string
    {
        $p = $this->props($node);
        $html = (string)($p['content']['html'] ?? $p['html'] ?? $p['content'] ?? $node['html'] ?? $node['content'] ?? '');
        return $html === '' ? '' : '<div ' . $this->attrs($node, 'tf-html') . '>' . $html . '</div>';
    }

    protected function image(array $node): string
    {
        $p = $this->props($node);
        $src = (string)($p['content']['media_id'] ?? $p['media_id'] ?? $p['src'] ?? $node['media_id'] ?? $node['src'] ?? '');
        if ($src === '') return '';
        if (!str_starts_with($src, '/') && !preg_match('~^https?://~i', $src)) $src = '/media/' . ltrim($src, '/');

        $alt = (string)($p['content']['alt'] ?? $p['alt'] ?? $node['alt'] ?? '');
        $caption = (string)($p['content']['caption'] ?? $p['caption'] ?? $node['caption'] ?? '');
        $url = (string)($p['behavior']['link_url'] ?? $p['link_url'] ?? $p['url'] ?? $node['link_url'] ?? $node['url'] ?? '');
        $target = (string)($p['behavior']['link_target'] ?? $p['link_target'] ?? $p['target'] ?? $node['link_target'] ?? $node['target'] ?? '_self');

        $img = '<img src="' . $this->e($src) . '" alt="' . $this->e($alt) . '" loading="lazy">';
        if ($url !== '') $img = '<a href="' . $this->e($url) . '" target="' . $this->e($target) . '">' . $img . '</a>';
        if ($caption !== '') $img .= '<figcaption>' . $this->e($caption) . '</figcaption>';
        return '<figure ' . $this->attrs($node, 'tf-image') . '>' . $img . '</figure>';
    }

    protected function button(array $node): string
    {
        $p = $this->props($node);
        $label = (string)($p['content']['label'] ?? $p['label'] ?? $p['text'] ?? $node['label'] ?? $node['text'] ?? $node['title'] ?? 'Button');
        $url = (string)($p['behavior']['url'] ?? $p['url'] ?? $node['url'] ?? '#');
        $target = (string)($p['behavior']['target'] ?? $p['target'] ?? $node['target'] ?? '_self');
        return '<div ' . $this->attrs($node, 'tf-button-wrap') . '><a class="tf-button" href="' . $this->e($url) . '" target="' . $this->e($target) . '">' . $this->e($label) . '</a></div>';
    }

    protected function reference(array $node): string
    {
        $p = $this->props($node);
        $source = (string)($p['source_node_id'] ?? $node['source_node_id'] ?? '');
        return '<div class="tf-reference-note">🔗 Referenz: ' . $this->e($source) . '</div>';
    }

    protected function attrs(array $node, string $class, string $extraStyle = ''): string
    {
        $p = $this->props($node);
        $container = (array)($p['container'] ?? []);
        $id = (string)($node['id'] ?? '');

        $classes = [$class, 'tf-node'];
        if ($id !== '') $classes[] = 'tf-node-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);

        $cssClass = (string)($p['advanced']['css_class'] ?? $p['css_class'] ?? $container['css_class'] ?? '');
        foreach (preg_split('/\s+/', trim($cssClass)) ?: [] as $c) {
            if ($c !== '') $classes[] = preg_replace('/[^a-zA-Z0-9_-]/', '-', $c);
        }

        $style = $this->style($container, (array)($p['layout'] ?? []), (array)($p['spacing'] ?? []), (array)($p['design'] ?? [])) . $extraStyle;
        $attrs = 'class="' . $this->e(implode(' ', array_unique($classes))) . '" data-node-id="' . $this->e($id) . '"';

        $cssId = (string)($p['advanced']['css_id'] ?? $p['css_id'] ?? $container['css_id'] ?? '');
        if ($cssId !== '') $attrs .= ' id="' . $this->e($cssId) . '"';
        if ($style !== '') $attrs .= ' style="' . $this->e($style) . '"';

        return $attrs;
    }

    protected function style(array $container, array $layout, array $spacing, array $design): string
    {
        $map = [
            'display' => ['display', $layout['display'] ?? $container['display'] ?? ''],
            'width' => ['width', $layout['width'] ?? $container['width'] ?? ''],
            'max-width' => ['max_width', $layout['max_width'] ?? $container['max_width'] ?? ''],
            'min-height' => ['min_height', $layout['min_height'] ?? $container['min_height'] ?? ''],
            'margin' => ['margin', $spacing['margin'] ?? $container['margin'] ?? ''],
            'padding' => ['padding', $spacing['padding'] ?? $container['padding'] ?? ''],
            'gap' => ['gap', $spacing['gap'] ?? $container['gap'] ?? ''],
            'background' => ['background', $design['background'] ?? $container['background'] ?? ''],
            'border' => ['border', $design['border'] ?? $container['border'] ?? ''],
            'border-radius' => ['border_radius', $design['border_radius'] ?? $design['radius'] ?? $container['border_radius'] ?? ''],
            'box-shadow' => ['box_shadow', $design['box_shadow'] ?? $design['shadow'] ?? $container['box_shadow'] ?? ''],
        ];
        $style = '';
        foreach ($map as $cssKey => [, $value]) {
            $value = (string)$value;
            if ($value !== '') $style .= $cssKey . ':' . $this->css($value) . ';';
        }
        return $style;
    }

    protected function scheduleActive(array $node): bool
    {
        $p = $this->props($node);
        $s = (array)($p['schedule'] ?? $node['schedule'] ?? []);
        $tz = new \DateTimeZone((string)($s['timezone'] ?? 'Europe/Berlin'));
        $now = new \DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');
        $time = $now->format('H:i');
        $dayMap = ['Mon'=>'mo','Tue'=>'tu','Wed'=>'we','Thu'=>'th','Fri'=>'fr','Sat'=>'sa','Sun'=>'su'];
        $day = $dayMap[$now->format('D')] ?? '';

        if (($s['active_from'] ?? '') !== '' && $today < $s['active_from']) return false;
        if (($s['active_until'] ?? '') !== '' && $today > $s['active_until']) return false;
        if (!empty($s['days']) && !in_array($day, (array)$s['days'], true)) return false;
        if (($s['time_from'] ?? '') !== '' && $time < $s['time_from']) return false;
        if (($s['time_until'] ?? '') !== '' && $time > $s['time_until']) return false;
        return true;
    }

    protected function isRenderable(array $node): bool
    {
        return ($node['status'] ?? 'active') === 'active' && ($node['visibility'] ?? 'visible') === 'visible';
    }

    protected function props(array $node): array
    {
        return isset($node['properties']) && is_array($node['properties']) ? $node['properties'] : [];
    }

    protected function type(array $node): string
    {
        return match (strtolower((string)($node['type'] ?? 'Node'))) {
            'root', 'rootnode', 'page', 'pagenode' => 'RootNode',
            'container', 'containernode' => 'ContainerNode',
            'schedule', 'schedulecontainer', 'schedulecontainernode' => 'ScheduleContainerNode',
            'columns', 'columnsnode' => 'ColumnsNode',
            'column', 'columnnode', 'col' => 'ColumnNode',
            'text', 'textnode' => 'TextNode',
            'markdown', 'markdownnode' => 'MarkdownNode',
            'html', 'htmlnode' => 'HtmlNode',
            'css', 'cssnode' => 'CssNode',
            'image', 'imagenode' => 'ImageNode',
            'button', 'buttonnode' => 'ButtonNode',
            'reference', 'referencenode' => 'ReferenceNode',
            default => (string)($node['type'] ?? 'Node'),
        };
    }

    protected function css(string $value): string
    {
        return str_replace(['"', "'", '<', '>', '{', '}'], '', trim($value));
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}