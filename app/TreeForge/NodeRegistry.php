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