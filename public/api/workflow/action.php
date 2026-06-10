<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

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

    $action = (string)($payload['action'] ?? '');
    $pageId = (string)($payload['page'] ?? 'home');

    $workspace = new Workspace($root, Workspace::DRAFT);

    switch ($action) {
        case 'send_to_review':
            $workspace->sendDraftToReview($pageId);
            $message = 'Draft wurde in den Review Workspace kopiert.';
            $target = 'review';
            break;

        case 'return_to_draft':
            $workspace->returnReviewToDraft($pageId);
            $message = 'Review wurde zurück in Draft kopiert.';
            $target = 'draft';
            break;

        case 'publish_review':
            $workspace->publishFromReview($pageId);
            $message = 'Review wurde veröffentlicht. Alte Published-Version wurde archiviert.';
            $target = 'published';
            break;

        default:
            throw new RuntimeException("Unknown workflow action: {$action}");
    }

    echo json_encode([
        'ok' => true,
        'action' => $action,
        'page' => $pageId,
        'target' => $target,
        'message' => $message,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}