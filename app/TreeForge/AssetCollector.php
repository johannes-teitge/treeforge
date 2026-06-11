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