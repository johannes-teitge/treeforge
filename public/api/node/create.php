<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Core\NodeCreator;
use TreeForge\Core\NodeInspector;
use TreeForge\Core\Workspace;

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Only POST allowed');
    }

    $root = dirname(__DIR__, 3);
    $payload = json_decode((string)file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        throw new RuntimeException('Invalid JSON payload');
    }

    $pageId = (string)($payload['page'] ?? 'home');
    $parentId = (string)($payload['parent'] ?? '');
    $type = (string)($payload['type'] ?? '');
    $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];

    if ($type === '') {
        throw new RuntimeException('Missing node type');
    }

    $workspace = Workspace::draft($root);
    $workspace->ensurePage($pageId);

    $pageData = $workspace->loadPageArray($pageId);
    $newNode = NodeCreator::createNode($type, $options);

    NodeCreator::appendNode($pageData, $parentId, $newNode);

    $pageData['_workflow'] = [
        'status' => 'draft_changed',
        'action' => 'node_created',
        'created_at' => date('c'),
    ];

    $workspace->savePage($pageId, $pageData);

    echo json_encode([
        'ok' => true,
        'message' => 'Node wurde im Draft angelegt.',
        'workspace' => 'draft',
        'page' => $pageId,
        'parent' => $parentId,
        'node' => $newNode['id'],
        'type' => $type,
        'inspector' => NodeInspector::inspectArray($newNode),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}