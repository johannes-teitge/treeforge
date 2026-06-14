<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\ExplorerV2\NodeMutationService;

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 3);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Nur POST erlaubt.');
    }

    $raw = (string)file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!is_array($body)) {
        $body = $_POST;
    }

    $areaId = (string)($body['area'] ?? '');
    $pageId = (string)($body['page'] ?? 'home');
    $kind = strtolower((string)($body['kind'] ?? ''));

    if ($areaId !== '') {
        $targetId = $areaId;
        $kind = 'area';
    } else {
        $targetId = $pageId;
        $kind = $kind === 'area' ? 'area' : 'page';
    }

    $workspace = (string)($body['workspace'] ?? 'draft');
    $action = (string)($body['action'] ?? '');
    $payload = (array)($body['payload'] ?? []);

    if ($action === '') {
        throw new RuntimeException('Action fehlt.');
    }

    $service = new NodeMutationService($root);
    $result = $service->mutate($targetId, $workspace, $action, $payload, $kind);

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}