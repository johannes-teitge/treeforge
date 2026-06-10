<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Core\NodeInspector;
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

    $workspace = Workspace::draft($root);
    $workspace->ensurePage($pageId);

    $file = $workspace->pagePath($pageId);

    if (!file_exists($file)) {
        throw new RuntimeException('Draft page file not found: ' . $file);
    }

    if (!is_writable($file)) {
        throw new RuntimeException('Draft page file is not writable: ' . $file);
    }

    $pageData = json_decode((string)file_get_contents($file), true);

    if (!is_array($pageData)) {
        throw new RuntimeException('Invalid page JSON');
    }

    $updatedNode = PageEditor::updateTextNodeContent($pageData, $nodeId, $content);

    $json = json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('Could not encode page JSON');
    }

    $bytes = file_put_contents($file, $json, LOCK_EX);

    if ($bytes === false) {
        throw new RuntimeException('Could not write draft page file: ' . $file);
    }

    clearstatcache(true, $file);

    $verifyData = json_decode((string)file_get_contents($file), true);

    if (!is_array($verifyData)) {
        throw new RuntimeException('Verification failed: saved file is invalid JSON');
    }

    $verifiedNode = PageEditor::updateTextNodeContent($verifyData, $nodeId, $content);
    // Achtung: Die Zeile oben sucht und setzt im Verifikationsarray erneut.
    // Für die Prüfung ist wichtig, ob der Inhalt im geladenen Array existiert.
    $verifiedContent = (string)($verifiedNode['content'] ?? '');

    if ($verifiedContent !== $content) {
        throw new RuntimeException('Verification failed: content was not persisted');
    }

    echo json_encode([
        'ok' => true,
        'message' => 'TextNode im Draft gespeichert.',
        'workspace' => 'draft',
        'page' => $pageId,
        'node' => $nodeId,
        'saved_file' => $file,
        'bytes_written' => $bytes,
        'file_mtime' => date('c', filemtime($file) ?: time()),
        'verified_content' => $verifiedContent,
        'inspector' => NodeInspector::inspectArray($updatedNode),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}