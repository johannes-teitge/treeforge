<?php
declare(strict_types=1);

use TreeForge\Core\NodeRegistry;
use TreeForge\Nodes\TextNode;
use TreeForge\Nodes\ImageNode;
use TreeForge\Nodes\ButtonNode;
use TreeForge\Nodes\ColumnsNode;

NodeRegistry::register('text', TextNode::class);
NodeRegistry::register('image', ImageNode::class);
NodeRegistry::register('button', ButtonNode::class);
NodeRegistry::register('columns', ColumnsNode::class);