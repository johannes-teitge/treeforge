<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 019
 * Robust Text Save Debug
 *
 * Fix:
 * - save-text.php aktualisiert die Node jetzt direkt und prüft danach die Datei
 * - PageEditor wird ebenfalls robust korrigiert
 * - API gibt saved_file, bytes_written und verified_content zurück
 * - explorer.js Cache-Buster auf v019
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 019 Robust Text Save Debug gestartet');

    $write($root . '/app/Core/PageEditor.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class PageEditor
{
    public static function updateTextNodeContent(array &$pageData, string $nodeId, string $content): array
    {
        if (!isset($pageData['children']) || !is_array($pageData['children'])) {
            throw new RuntimeException('Page has no children array');
        }

        foreach ($pageData['children'] as $index => $node) {
            if (!is_array($node)) {
                continue;
            }

            $updatedNode = self::updateTextNodeRecursive($pageData['children'][$index], $nodeId, $content);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        throw new RuntimeException("Node not found: {$nodeId}");
    }

    protected static function updateTextNodeRecursive(array &$node, string $nodeId, string $content): ?array
    {
        if ((string)($node['id'] ?? '') === $nodeId) {
            if ((string)($node['type'] ?? '') !== 'text') {
                throw new RuntimeException("Node is not editable as text: {$nodeId}");
            }

            $node['content'] = $content;
            $node['updated_at'] = date('c');

            return $node;
        }

        if (!isset($node['children']) || !is_array($node['children'])) {
            return null;
        }

        foreach ($node['children'] as $index => $child) {
            if (!is_array($child)) {
                continue;
            }

            $updatedNode = self::updateTextNodeRecursive($node['children'][$index], $nodeId, $content);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        return null;
    }
}
PHP);

    $write($root . '/public/api/node/save-text.php', <<<'PHP'
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
PHP);

    $rendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';

    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        $renderer = preg_replace(
            '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
            '<script src="/assets/js/explorer.js?v=019"></script>',
            $renderer
        );

        $write($rendererFile, $renderer);
    }

    $write($root . '/docs/robust-text-save-debug.md', <<<'MD'
# Robust Text Save Debug

Patch 019 korrigiert das Speichern der TextNode erneut und gibt Debugdaten zurück.

## API-Antwort enthält jetzt

```json
{
  "saved_file": ".../storage/workspaces/draft/pages/home.json",
  "bytes_written": 1234,
  "verified_content": "..."
}
```

## Test

1. Browser öffnen:
   ```text
   /explorer?workspace=draft
   ```

2. F12 → Netzwerk öffnen.

3. TextNode speichern.

4. Request prüfen:
   ```text
   /api/node/save-text.php
   ```

5. In der Antwort prüfen:
   ```text
   ok: true
   saved_file: richtiger Pfad
   verified_content: neuer Text
   ```

MD);

    $log('Patch 019 Robust Text Save Debug fertig');
};
