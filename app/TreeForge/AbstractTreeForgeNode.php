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