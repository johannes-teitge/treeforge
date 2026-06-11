<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 031
 * TreeForge Core Foundation
 *
 * Ziel:
 * - TreeForge-Basisklassen vorbereiten
 * - keine bestehenden Editor-Funktionen umbauen
 * - Grundlage für NodeRegistry, Manifest, Validator, Assets und EditorSchema
 *
 * Dateien:
 * - app/TreeForge/AbstractTreeForgeNode.php
 * - app/TreeForge/NodeRegistry.php
 * - app/TreeForge/NodeManifest.php
 * - app/TreeForge/NodeValidator.php
 * - app/TreeForge/AssetCollector.php
 * - app/TreeForge/EditorSchemaBuilder.php
 * - app/TreeForge/NodeCapabilities.php
 * - app/TreeForge/Nodes/DemoNode.php
 * - docs/treeforge/12-core-foundation.md
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

    $log('Patch 031 TreeForge Core Foundation gestartet');

    $write($root . '/app/TreeForge/AbstractTreeForgeNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace App\TreeForge;

abstract class AbstractTreeForgeNode
{
    public string $type = '';
    public string $label = '';
    public string $icon = 'fa-solid fa-cube';
    public string $category = 'Content';
    public string $version = '1.0.0';

    public bool $hasChildren = false;

    /**
     * Erlaubte Child-Node-Typen.
     * ['*'] bedeutet: alle registrierten Nodes erlaubt.
     */
    public array $allowedChildren = [];

    /**
     * Erlaubte Parent-Node-Typen.
     * [] bedeutet: keine Einschränkung.
     */
    public array $allowedParents = [];

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label ?: $this->type;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function hasChildren(): bool
    {
        return $this->hasChildren;
    }

    public function getAllowedChildren(): array
    {
        return $this->allowedChildren;
    }

    public function getAllowedParents(): array
    {
        return $this->allowedParents;
    }

    public function getBaseData(): array
    {
        return [
            'active' => true,
            'valid_from' => null,
            'valid_until' => null,
            'css_id' => '',
            'css_class' => '',
        ];
    }

    public function getCapabilities(): array
    {
        return NodeCapabilities::defaults();
    }

    public function getAssets(): array
    {
        return [
            'editor_css' => null,
            'frontend_css' => null,
            'frontend_js' => null,
        ];
    }

    public function isRenderable(array $node): bool
    {
        if (array_key_exists('active', $node) && !$node['active']) {
            return false;
        }

        $now = time();

        if (!empty($node['valid_from']) && strtotime((string)$node['valid_from']) > $now) {
            return false;
        }

        if (!empty($node['valid_until']) && strtotime((string)$node['valid_until']) < $now) {
            return false;
        }

        return true;
    }

    public function getDefaultData(): array
    {
        return [];
    }

    public function getEditorSchema(): array
    {
        return [];
    }

    public function preview(array $data = []): string
    {
        return $this->render($data, []);
    }

    public function migrate(array $data, string $oldVersion): array
    {
        return $data;
    }

    abstract public function render(array $data, array $children = []): string;
}
PHP);

    $write($root . '/app/TreeForge/NodeCapabilities.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace App\TreeForge;

final class NodeCapabilities
{
    public static function defaults(): array
    {
        return [
            'renderable' => true,
            'editable' => true,
            'sortable' => true,
            'cloneable' => true,
            'deletable' => true,
        ];
    }

    public static function root(): array
    {
        return [
            'renderable' => true,
            'editable' => false,
            'sortable' => false,
            'cloneable' => false,
            'deletable' => false,
        ];
    }
}
PHP);

    $write($root . '/app/TreeForge/NodeManifest.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace App\TreeForge;

final class NodeManifest
{
    public static function load(string $file): array
    {
        if (!file_exists($file)) {
            throw new \RuntimeException('Node manifest not found: ' . $file);
        }

        $json = file_get_contents($file);
        $data = json_decode((string)$json, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid node manifest: ' . $file);
        }

        foreach (['type', 'class', 'file', 'label'] as $required) {
            if (empty($data[$required])) {
                throw new \RuntimeException('Missing manifest field "' . $required . '" in ' . $file);
            }
        }

        $data['_manifest_file'] = $file;
        $data['_base_path'] = dirname($file);

        return $data;
    }
}
PHP);

    $write($root . '/app/TreeForge/NodeRegistry.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace App\TreeForge;

final class NodeRegistry
{
    /**
     * @var array<string, AbstractTreeForgeNode>
     */
    private array $nodes = [];

    public function register(AbstractTreeForgeNode $node): void
    {
        $type = $node->getType();

        if ($type === '') {
            throw new \InvalidArgumentException('TreeForge node type must not be empty.');
        }

        $this->nodes[$type] = $node;
    }

    public function has(string $type): bool
    {
        return isset($this->nodes[$type]);
    }

    public function get(string $type): AbstractTreeForgeNode
    {
        if (!$this->has($type)) {
            throw new \InvalidArgumentException('Unknown TreeForge node type: ' . $type);
        }

        return $this->nodes[$type];
    }

    public function all(): array
    {
        return $this->nodes;
    }

    public function groupedByCategory(): array
    {
        $groups = [];

        foreach ($this->nodes as $type => $node) {
            $groups[$node->getCategory()][$type] = $node;
        }

        ksort($groups);

        return $groups;
    }

    public function scanDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $manifestFiles = glob(rtrim($directory, '/') . '/*/node.json') ?: [];

        foreach ($manifestFiles as $manifestFile) {
            $manifest = NodeManifest::load($manifestFile);

            if (isset($manifest['active']) && !$manifest['active']) {
                continue;
            }

            $nodeFile = $manifest['_base_path'] . '/' . $manifest['file'];

            if (!file_exists($nodeFile)) {
                throw new \RuntimeException('Node class file not found: ' . $nodeFile);
            }

            require_once $nodeFile;

            $class = $manifest['class'];

            if (!class_exists($class)) {
                throw new \RuntimeException('Node class not found: ' . $class);
            }

            $instance = new $class($manifest);

            if (!$instance instanceof AbstractTreeForgeNode) {
                throw new \RuntimeException('Node must extend AbstractTreeForgeNode: ' . $class);
            }

            $this->register($instance);
        }
    }
}
PHP);

    $write($root . '/app/TreeForge/NodeValidator.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace App\TreeForge;

final class NodeValidator
{
    public function __construct(
        private NodeRegistry $registry
    ) {
    }

    public function canAddChild(string $parentType, string $childType): bool
    {
        if (!$this->registry->has($parentType) || !$this->registry->has($childType)) {
            return false;
        }

        $parent = $this->registry->get($parentType);
        $child = $this->registry->get($childType);

        if (!$parent->hasChildren()) {
            return false;
        }

        $allowedChildren = $parent->getAllowedChildren();

        if (!in_array('*', $allowedChildren, true) && !in_array($childType, $allowedChildren, true)) {
            return false;
        }

        $allowedParents = $child->getAllowedParents();

        if ($allowedParents !== [] && !in_array($parentType, $allowedParents, true)) {
            return false;
        }

        return true;
    }
}
PHP);

    $write($root . '/app/TreeForge/AssetCollector.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace App\TreeForge;

final class AssetCollector
{
    private array $css = [];
    private array $js = [];

    public function addCss(?string $path): void
    {
        if ($path) {
            $this->css[$path] = $path;
        }
    }

    public function addJs(?string $path): void
    {
        if ($path) {
            $this->js[$path] = $path;
        }
    }

    public function addNodeAssets(AbstractTreeForgeNode $node): void
    {
        $assets = $node->getAssets();

        $this->addCss($assets['frontend_css'] ?? null);
        $this->addJs($assets['frontend_js'] ?? null);
    }

    public function getCss(): array
    {
        return array_values($this->css);
    }

    public function getJs(): array
    {
        return array_values($this->js);
    }

    public function renderCss(): string
    {
        $html = '';

        foreach ($this->getCss() as $href) {
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }

        return $html;
    }

    public function renderJs(): string
    {
        $html = '';

        foreach ($this->getJs() as $src) {
            $html .= '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        }

        return $html;
    }
}
PHP);

    $write($root . '/app/TreeForge/EditorSchemaBuilder.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace App\TreeForge;

final class EditorSchemaBuilder
{
    public function build(AbstractTreeForgeNode $node): array
    {
        return [
            'type' => $node->getType(),
            'label' => $node->getLabel(),
            'icon' => $node->getIcon(),
            'category' => $node->getCategory(),
            'version' => $node->getVersion(),
            'hasChildren' => $node->hasChildren(),
            'allowedChildren' => $node->getAllowedChildren(),
            'allowedParents' => $node->getAllowedParents(),
            'capabilities' => $node->getCapabilities(),
            'baseData' => $node->getBaseData(),
            'defaultData' => $node->getDefaultData(),
            'schema' => $node->getEditorSchema(),
            'assets' => $node->getAssets(),
        ];
    }
}
PHP);

    $write($root . '/app/TreeForge/Nodes/DemoNode.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace App\TreeForge\Nodes;

use App\TreeForge\AbstractTreeForgeNode;

final class DemoNode extends AbstractTreeForgeNode
{
    public string $type = 'demo';
    public string $label = 'Demo Node';
    public string $icon = 'fa-solid fa-puzzle-piece';
    public string $category = 'Demo';
    public string $version = '1.0.0';

    public bool $hasChildren = false;

    public function getDefaultData(): array
    {
        return [
            'headline' => 'Demo Überschrift',
            'text' => 'Demo Text',
            'style' => 'info',
            'show_icon' => true,
        ];
    }

    public function getEditorSchema(): array
    {
        return [
            [
                'tab' => 'Inhalt',
                'fields' => [
                    [
                        'name' => 'headline',
                        'label' => 'Überschrift',
                        'type' => 'text',
                    ],
                    [
                        'name' => 'text',
                        'label' => 'Text',
                        'type' => 'textarea',
                    ],
                ],
            ],
            [
                'tab' => 'Design',
                'fields' => [
                    [
                        'name' => 'style',
                        'label' => 'Darstellung',
                        'type' => 'select',
                        'options' => [
                            'info' => 'Info',
                            'success' => 'Erfolg',
                            'warning' => 'Warnung',
                            'danger' => 'Fehler',
                        ],
                    ],
                    [
                        'name' => 'show_icon',
                        'label' => 'Icon anzeigen',
                        'type' => 'checkbox',
                    ],
                ],
            ],
        ];
    }

    public function getAssets(): array
    {
        return [
            'editor_css' => null,
            'frontend_css' => null,
            'frontend_js' => null,
        ];
    }

    public function render(array $data, array $children = []): string
    {
        $headline = htmlspecialchars((string)($data['headline'] ?? ''), ENT_QUOTES, 'UTF-8');
        $text = nl2br(htmlspecialchars((string)($data['text'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $style = htmlspecialchars((string)($data['style'] ?? 'info'), ENT_QUOTES, 'UTF-8');

        return '<div class="tf-node-demo tf-demo-' . $style . '">' .
            '<h3>' . $headline . '</h3>' .
            '<p>' . $text . '</p>' .
            '</div>';
    }
}
PHP);

    $write($root . '/docs/treeforge/12-core-foundation.md', <<<'MD'
# TreeForge Core Foundation

Patch 031 legt die ersten Core-Basisklassen an.

## Dateien

```text
app/TreeForge/
├── AbstractTreeForgeNode.php
├── NodeRegistry.php
├── NodeManifest.php
├── NodeValidator.php
├── AssetCollector.php
├── EditorSchemaBuilder.php
├── NodeCapabilities.php
└── Nodes/
    └── DemoNode.php
```

## Ziel

Dieser Patch verändert den bestehenden Editor noch nicht.

Er schafft nur die Grundlage für:

- zentrale Node-Definitionen
- automatische Node-Registrierung
- Drop-in-Nodes
- Editor-Schema
- Asset-Sammlung
- Parent/Child-Regeln
- DemoNode als Entwicklerbeispiel

## Nächster Schritt

Im nächsten Patch können bestehende Nodes wie Columns, Column, Text, Image und Markdown schrittweise auf die neue Struktur vorbereitet werden.
MD);

    $log('Patch 031 TreeForge Core Foundation fertig');
};
