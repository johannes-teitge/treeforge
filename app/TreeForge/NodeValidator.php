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