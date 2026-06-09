<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Core\PageEditor;
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
    $nodeId = (string)($payload['node'] ?? '');
    $content = (string)($payload['content'] ?? '');

    if ($nodeId === '') {
        throw new RuntimeException('Missing node id');
    }

    // Sicherheit: Bearbeitung geht vorerst immer nur in draft.
    $workspace = Workspace::draft($root);
    $workspace->ensurePage($pageId);

    $file = $workspace->pagePath($pageId);
    $pageData = json_decode((string)file_get_contents($file), true);

    if (!is_array($pageData)) {
        throw new RuntimeException('Invalid page JSON');
    }

    $updated = PageEditor::updateTextNodeContent($pageData, $nodeId, $content);

    if (!$updated) {
        throw new RuntimeException("Node not found: {$nodeId}");
    }

    $workspace->savePage($pageId, $pageData);

    echo json_encode([
        'ok' => true,
        'message' => 'TextNode im Draft gespeichert.',
        'workspace' => 'draft',
        'page' => $pageId,
        'node' => $nodeId,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}