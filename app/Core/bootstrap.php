<?php
declare(strict_types=1);

use TreeForge\Core\NodeRegistry;
use TreeForge\Nodes\TextNode;
use TreeForge\Nodes\ImageNode;
use TreeForge\Nodes\ButtonNode;
use TreeForge\Nodes\ColumnsNode;
use TreeForge\Nodes\ColumnNode;
use TreeForge\Nodes\CssNode;
use TreeForge\Nodes\MarkdownNode;

NodeRegistry::register('text', TextNode::class);
NodeRegistry::register('image', ImageNode::class);
NodeRegistry::register('button', ButtonNode::class);
NodeRegistry::register('columns', ColumnsNode::class);
NodeRegistry::register('column', ColumnNode::class);
NodeRegistry::register('css', CssNode::class);
NodeRegistry::register('markdown', MarkdownNode::class);