<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 026
 * Markdown Inspector Preview + Editor
 *
 * - MarkdownNode bekommt im Backend einen Editor
 * - Markdown wird im Inspector als gerenderte Preview angezeigt
 * - Speichern läuft async über API ohne Reload
 * - API speichert nur im Draft Workspace
 *
 * Voraussetzung:
 * composer require league/commonmark
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

    $log('Patch 026 Markdown Inspector Preview + Editor gestartet');

    $write($root . '/app/Core/PageEditor.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class PageEditor
{
    public static function updateTextNodeContent(array &$pageData, string $nodeId, string $content): array
    {
        return self::updateNodeContent($pageData, $nodeId, $content, ['text']);
    }

    public static function updateMarkdownNodeContent(array &$pageData, string $nodeId, string $content): array
    {
        return self::updateNodeContent($pageData, $nodeId, $content, ['markdown']);
    }

    public static function updateNodeContent(array &$pageData, string $nodeId, string $content, array $allowedTypes): array
    {
        if (!isset($pageData['children']) || !is_array($pageData['children'])) {
            throw new RuntimeException('Page has no children array');
        }

        foreach ($pageData['children'] as $index => $node) {
            if (!is_array($node)) {
                continue;
            }

            $updatedNode = self::updateNodeContentRecursive($pageData['children'][$index], $nodeId, $content, $allowedTypes);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        throw new RuntimeException("Node not found: {$nodeId}");
    }

    protected static function updateNodeContentRecursive(array &$node, string $nodeId, string $content, array $allowedTypes): ?array
    {
        if ((string)($node['id'] ?? '') === $nodeId) {
            $type = (string)($node['type'] ?? '');

            if (!in_array($type, $allowedTypes, true)) {
                throw new RuntimeException("Node type is not editable here: {$nodeId} / {$type}");
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

            $updatedNode = self::updateNodeContentRecursive($node['children'][$index], $nodeId, $content, $allowedTypes);

            if ($updatedNode !== null) {
                return $updatedNode;
            }
        }

        return null;
    }
}
PHP);

    $write($root . '/app/Core/MarkdownRenderer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use League\CommonMark\CommonMarkConverter;

class MarkdownRenderer
{
    protected static ?CommonMarkConverter $converter = null;

    public static function toHtml(string $markdown): string
    {
        return self::converter()->convert($markdown)->getContent();
    }

    protected static function converter(): CommonMarkConverter
    {
        if (self::$converter === null) {
            self::$converter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        return self::$converter;
    }
}
PHP);

    $write($root . '/app/Core/InspectorPreviewRenderer.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

class InspectorPreviewRenderer
{
    public static function render(array $node): array
    {
        $type = (string)($node['type'] ?? 'unknown');

        return match ($type) {
            'css' => self::codePreview($node, 'css'),
            'markdown' => self::markdownPreview($node),
            default => [
                'kind' => 'none',
                'language' => '',
                'content' => '',
                'html' => '',
            ],
        };
    }

    protected static function codePreview(array $node, string $language): array
    {
        return [
            'kind' => 'code',
            'language' => $language,
            'content' => (string)($node['content'] ?? ''),
            'html' => '',
        ];
    }

    protected static function markdownPreview(array $node): array
    {
        $content = (string)($node['content'] ?? '');

        return [
            'kind' => 'markdown',
            'language' => 'markdown',
            'content' => $content,
            'html' => MarkdownRenderer::toHtml($content),
        ];
    }
}
PHP);

    $write($root . '/public/api/node/save-markdown.php', <<<'PHP'
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

    $updatedNode = PageEditor::updateMarkdownNodeContent($pageData, $nodeId, $content);

    $json = json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new RuntimeException('Could not encode page JSON');
    }

    $bytes = file_put_contents($file, $json, LOCK_EX);

    if ($bytes === false) {
        throw new RuntimeException('Could not write draft page file: ' . $file);
    }

    clearstatcache(true, $file);

    echo json_encode([
        'ok' => true,
        'message' => 'MarkdownNode im Draft gespeichert.',
        'workspace' => 'draft',
        'page' => $pageId,
        'node' => $nodeId,
        'saved_file' => $file,
        'bytes_written' => $bytes,
        'file_mtime' => date('c', filemtime($file) ?: time()),
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

    $rendererFile = $root . '/app/Renderer/HtmlRenderer.php';
    if (file_exists($rendererFile)) {
        $renderer = file_get_contents($rendererFile);

        if (!str_contains($renderer, 'use TreeForge\\Core\\MarkdownRenderer;')) {
            $renderer = str_replace(
                "use TreeForge\\Core\\Config;\n",
                "use TreeForge\\Core\\Config;\nuse TreeForge\\Core\\MarkdownRenderer;\n",
                $renderer
            );
        }

        $renderer = preg_replace(
            '#protected function renderMarkdown\(MarkdownNode \$node\): string\s*\{.*?\n    \}#s',
            <<<'PHP'
protected function renderMarkdown(MarkdownNode $node): string
    {
        $html = MarkdownRenderer::toHtml($node->content());

        return <<<HTML
<div class="tf-node tf-node-markdown">
  {$html}
</div>
HTML;
    }
PHP,
            $renderer
        );

        $write($rendererFile, $renderer);
    }

    $explorerRendererFile = $root . '/app/Modules/Explorer/ExplorerRenderer.php';
    if (file_exists($explorerRendererFile)) {
        $renderer = file_get_contents($explorerRendererFile);

        if (!str_contains($renderer, 'id="tfMarkdownEditorSection"')) {
            $renderer = str_replace(
                <<<'HTML'
        <section class="tf-inspector-section" id="tfPreviewSection" hidden>
          <h3>Preview</h3>
          <pre class="tf-code-preview"><code id="tfPreviewCode"></code></pre>
        </section>
HTML,
                <<<'HTML'
        <section class="tf-inspector-section tf-editor-section" id="tfMarkdownEditorSection" hidden>
          <h3>Markdown Editor</h3>
          <p class="tf-editor-hint">Speichert immer in den Draft Workspace.</p>
          <textarea id="tfMarkdownEditor" class="tf-textarea" rows="10"></textarea>
          <div class="tf-editor-actions">
            <button type="button" id="tfSaveMarkdownNode" class="tf-action-button">Markdown in Draft speichern</button>
            <span id="tfMarkdownSaveStatus" class="tf-save-status"></span>
          </div>
        </section>

        <section class="tf-inspector-section" id="tfPreviewSection" hidden>
          <h3>Preview</h3>
          <div id="tfMarkdownPreview" class="tf-markdown-preview" hidden></div>
          <pre class="tf-code-preview"><code id="tfPreviewCode"></code></pre>
        </section>
HTML,
                $renderer
            );
        }

        $renderer = preg_replace(
            '#<script src="/assets/js/explorer\.js(?:\?v=\d+)?"></script>#',
            '<script src="/assets/js/explorer.js?v=026"></script>',
            $renderer
        );

        $write($explorerRendererFile, $renderer);
    }

    $jsFile = $root . '/public/assets/js/explorer.js';
    if (file_exists($jsFile)) {
        $js = file_get_contents($jsFile);

        $js = str_replace(
            "const previewCode = document.getElementById('tfPreviewCode');",
            "const previewCode = document.getElementById('tfPreviewCode');\n  const markdownPreview = document.getElementById('tfMarkdownPreview');\n  const markdownEditorSection = document.getElementById('tfMarkdownEditorSection');\n  const markdownEditor = document.getElementById('tfMarkdownEditor');\n  const saveMarkdownButton = document.getElementById('tfSaveMarkdownNode');\n  const markdownSaveStatus = document.getElementById('tfMarkdownSaveStatus');",
            $js
        );

        $js = preg_replace(
            '#function renderPreview\(preview\) \{.*?\n  \}#s',
            <<<'JS'
function renderPreview(preview) {
    if (markdownPreview) {
      markdownPreview.hidden = true;
      markdownPreview.innerHTML = '';
    }

    previewCode.textContent = '';
    previewCode.className = '';
    previewCode.parentElement.hidden = true;
    previewSection.hidden = true;

    if (!preview || !preview.kind || preview.kind === 'none') {
      return;
    }

    if (preview.kind === 'markdown') {
      if (markdownPreview) {
        markdownPreview.innerHTML = preview.html || '';
        markdownPreview.hidden = false;
      }

      previewSection.hidden = false;
      return;
    }

    if (preview.kind === 'code') {
      const lang = preview.language || 'markup';
      previewCode.textContent = preview.content || '';
      previewCode.className = 'language-' + lang;
      previewCode.parentElement.hidden = false;
      previewSection.hidden = false;

      if (window.Prism) {
        Prism.highlightElement(previewCode);
      }
    }
  }
JS,
            $js
        );

        $insertAfterTextEditor = <<<'JS'
  function renderMarkdownEditor(data) {
    const isMarkdown = data && data.type === 'markdown';
    const workspace = (window.TreeForgeExplorer && window.TreeForgeExplorer.workspace) || 'published';
    const archive = (window.TreeForgeExplorer && window.TreeForgeExplorer.archive) || '';

    if (!markdownEditorSection || !markdownEditor || !saveMarkdownButton) {
      return;
    }

    if (!isMarkdown || archive !== '') {
      markdownEditorSection.hidden = true;
      return;
    }

    markdownEditor.value = (data.properties && data.properties.content) ? data.properties.content : '';
    markdownEditorSection.hidden = false;

    if (workspace === 'draft') {
      saveMarkdownButton.disabled = false;
      inspectorMode.textContent = 'editable';
      markdownSaveStatus.textContent = '';
    } else {
      saveMarkdownButton.disabled = true;
      markdownSaveStatus.textContent = 'Zum Bearbeiten Draft Workspace öffnen.';
    }
  }

JS;

        if (!str_contains($js, 'function renderMarkdownEditor')) {
            $js = str_replace(
                "  function renderInspector(data, keepTextFocus) {",
                $insertAfterTextEditor . "  function renderInspector(data, keepTextFocus) {",
                $js
            );

            $js = str_replace(
                "    renderPreview(data.preview || {});",
                "    renderMarkdownEditor(data);\n    renderPreview(data.preview || {});",
                $js
            );
        }

        if (!str_contains($js, "save-markdown.php")) {
            $saveMarkdown = <<<'JS'

  if (saveMarkdownButton) {
    saveMarkdownButton.addEventListener('click', async () => {
      if (!selectedNode || selectedNode.type !== 'markdown') {
        return;
      }

      const oldText = saveMarkdownButton.textContent;
      saveMarkdownButton.disabled = true;
      saveMarkdownButton.textContent = 'Speichere ...';
      markdownSaveStatus.textContent = 'Speichere im Draft ...';

      try {
        const response = await fetch('/api/node/save-markdown.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            page: (window.TreeForgeExplorer && window.TreeForgeExplorer.page) || 'home',
            node: selectedNode.id,
            content: markdownEditor.value
          })
        });

        const raw = await response.text();
        let result;

        try {
          result = JSON.parse(raw);
        } catch (parseError) {
          throw new Error('API liefert kein JSON: ' + raw.substring(0, 180));
        }

        if (!response.ok || !result.ok) {
          throw new Error(result.error || 'Fehler beim Speichern');
        }

        if (result.inspector) {
          selectedNode = result.inspector;
          updateSelectedButtonData(result.inspector);
          renderInspector(result.inspector, true);
        }

        markdownSaveStatus.textContent = 'Gespeichert.';
        showNotice('success', result.message || 'Gespeichert.');
        markdownEditor.focus();

      } catch (error) {
        markdownSaveStatus.textContent = error.message;
        showNotice('error', error.message);
      } finally {
        saveMarkdownButton.disabled = false;
        saveMarkdownButton.textContent = oldText;
      }
    });
  }
JS;

            $js = str_replace(
                "  document.querySelectorAll('[data-workflow-action]').forEach((button) => {",
                $saveMarkdown . "\n\n  document.querySelectorAll('[data-workflow-action]').forEach((button) => {",
                $js
            );
        }

        $write($jsFile, $js);
    }

    $cssFile = $root . '/public/assets/css/explorer.css';
    if (file_exists($cssFile)) {
        $css = file_get_contents($cssFile);

        if (!str_contains($css, '.tf-markdown-preview')) {
            $css .= <<<'CSS'

.tf-markdown-preview {
  background: #fff;
  border: 1px solid rgba(23, 63, 53, .1);
  border-radius: 1rem;
  padding: 1rem;
  line-height: 1.6;
}

.tf-markdown-preview h1,
.tf-markdown-preview h2,
.tf-markdown-preview h3 {
  color: var(--tf-green);
  margin-top: 0;
}

.tf-markdown-preview strong {
  color: var(--tf-green);
}

.tf-markdown-preview ul,
.tf-markdown-preview ol {
  padding-left: 1.35rem;
}

.tf-markdown-preview p:last-child,
.tf-markdown-preview ul:last-child,
.tf-markdown-preview ol:last-child {
  margin-bottom: 0;
}
CSS;

            $write($cssFile, $css);
        }
    }

    $write($root . '/docs/markdown-backend-editor.md', <<<'MD'
# Markdown Backend Editor

Patch 026 ergänzt Markdown im Backend.

## Funktionen

- MarkdownNode bekommt einen eigenen Editor im Inspector.
- Markdown Preview wird als echtes HTML gerendert.
- Speichern läuft async ohne Reload.
- Speichern erfolgt nur im Draft Workspace.

## API

```text
POST /api/node/save-markdown.php
```

## Sicherheit

Markdown wird mit `league/commonmark` und sicheren Optionen gerendert.

MD);

    $log('Patch 026 Markdown Inspector Preview + Editor fertig');
};
