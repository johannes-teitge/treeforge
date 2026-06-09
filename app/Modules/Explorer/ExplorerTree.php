<?php
declare(strict_types=1);

namespace TreeForge\Modules\Explorer;

use TreeForge\Core\NodeInspector;

class ExplorerTree
{
    public function renderPageTree(array $pageData): string
    {
        $title = htmlspecialchars((string)($pageData['title'] ?? $pageData['id'] ?? 'Page'), ENT_QUOTES, 'UTF-8');

        $html = '<ul class="tf-explorer-tree">';
        $html .= '<li class="tf-tree-page"><span class="tf-tree-label">🌳 ' . $title . '</span>';

        $children = $pageData['children'] ?? [];

        if (is_array($children) && $children !== []) {
            $html .= '<ul>';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= $this->renderNode($child);
                }
            }
            $html .= '</ul>';
        }

        $html .= '</li></ul>';

        return $html;
    }

    protected function renderNode(array $node): string
    {
        $id = htmlspecialchars((string)($node['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type = (string)($node['type'] ?? 'unknown');
        $icon = NodeInspector::typeIcon($type);
        $label = htmlspecialchars(NodeInspector::typeLabel($type), ENT_QUOTES, 'UTF-8');
        $inspect = NodeInspector::inspectArray($node);

        $json = htmlspecialchars(
            json_encode($inspect, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            ENT_QUOTES,
            'UTF-8'
        );

        $html = '<li class="tf-tree-node">';
        $html .= '<button class="tf-tree-node-button" type="button" data-node-json="' . $json . '">';
        $html .= '<span class="tf-node-main">' . $icon . ' ' . $label . '</span>';
        $html .= '<span class="tf-node-id">' . $id . '</span>';
        $html .= '</button>';

        $children = $node['children'] ?? [];

        if (is_array($children) && $children !== []) {
            $html .= '<ul>';
            foreach ($children as $child) {
                if (is_array($child)) {
                    $html .= $this->renderNode($child);
                }
            }
            $html .= '</ul>';
        }

        $html .= '</li>';

        return $html;
    }
}