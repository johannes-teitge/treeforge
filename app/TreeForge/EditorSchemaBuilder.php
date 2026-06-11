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