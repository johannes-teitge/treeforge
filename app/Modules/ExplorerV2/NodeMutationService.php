<?php
declare(strict_types=1);

namespace TreeForge\Modules\ExplorerV2;

class NodeMutationService
{
    protected NodeTypeRegistry $registry;
    protected array $activeNodeIds = [];

    public function __construct(protected string $root)
    {
        $this->registry = new NodeTypeRegistry();
    }

    public function mutate(string $pageId, string $workspace, string $action, array $payload, string $targetKind = 'page'): array
    {
        $pageId = $this->cleanId($pageId, 'home');
        $workspace = $this->cleanWorkspace($workspace);
        $targetKind = $this->cleanTargetKind($targetKind);
        $data = $this->loadContent($pageId, $workspace, $targetKind);
        $this->activeNodeIds = \TreeForge\Core\NodeIdGenerator::collectIds($data);

        $result = match ($action) {
            'add' => $this->addNode($data, $payload),
            'delete' => $this->deleteNode($data, $payload),
            'duplicate' => $this->duplicateNode($data, $payload),
            'paste-copy' => $this->pasteCopy($data, $payload),
            'paste-reference' => $this->pasteReference($data, $payload),
            'update-node' => $this->updateNode($data, $payload),
            default => throw new \RuntimeException('Unbekannte Mutation: ' . $action),
        };

        $data['updated_at'] = date('c');
        $this->saveContent($pageId, $workspace, $data, $targetKind);

        return [
            'ok' => true,
            'action' => $action,
            'page' => $pageId,
            'kind' => $targetKind,
            'workspace' => $workspace,
            'result' => $result,
            'pageData' => $data,
        ];
    }
    public function loadContent(string $id, string $workspace, string $targetKind = 'page'): array
    {
        $targetKind = $this->cleanTargetKind($targetKind);

        if ($targetKind === 'area') {
            $file = $this->areaFile($id, $workspace);

            if (!file_exists($file) && $workspace !== 'published') {
                $published = $this->areaFile($id, 'published');
                if (file_exists($published)) {
                    $data = json_decode((string)file_get_contents($published), true);
                    if (is_array($data)) {
                        return $this->normalizeAreaRoot($data, $id);
                    }
                }
            }

            if (!file_exists($file)) {
                return [
                    'id' => $id,
                    'type' => 'RootNode',
                    'kind' => 'area',
                    'title' => ucfirst($id),
                    'visibility' => 'hidden',
                    'system' => true,
                    'children' => [],
                    'created_at' => date('c'),
                    'updated_at' => date('c'),
                ];
            }

            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) {
                throw new \RuntimeException('Area JSON ist ungültig: ' . $file);
            }

            return $this->normalizeAreaRoot($data, $id);
        }

        return $this->loadPage($id, $workspace);
    }

    public function saveContent(string $id, string $workspace, array $data, string $targetKind = 'page'): void
    {
        $targetKind = $this->cleanTargetKind($targetKind);

        if ($targetKind === 'area') {
            $file = $this->areaFile($id, $workspace);

            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }

            $data = $this->normalizeAreaRoot($data, $id);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new \RuntimeException('Area JSON konnte nicht erzeugt werden: ' . json_last_error_msg());
            }

            $bytes = file_put_contents($file, $json, LOCK_EX);
            if ($bytes === false) {
                throw new \RuntimeException('Area JSON konnte nicht geschrieben werden: ' . $file);
            }

            return;
        }

        $this->savePage($id, $workspace, $data);
    }

    public function loadPage(string $pageId, string $workspace): array
    {
        $file = $this->pageFile($pageId, $workspace);
        if (!file_exists($file) && $workspace !== 'published') {
            $published = $this->pageFile($pageId, 'published');
            if (file_exists($published)) {
                $data = json_decode((string)file_get_contents($published), true);
                if (is_array($data)) {
                    return $this->normalizeRoot($data, $pageId);
                }
            }
        }

        if (!file_exists($file)) {
            $legacy = $this->root . '/storage/pages/' . $pageId . '.json';
            if (file_exists($legacy)) {
                $data = json_decode((string)file_get_contents($legacy), true);
                if (is_array($data)) {
                    return $this->normalizeRoot($data, $pageId);
                }
            }

            return [
                'id' => $pageId,
                'type' => 'RootNode',
                'title' => ucfirst($pageId),
                'children' => [],
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            throw new \RuntimeException('Page JSON ist ungültig: ' . $file);
        }
        return $this->normalizeRoot($data, $pageId);
    }

    public function savePage(string $pageId, string $workspace, array $data): void
    {
        $file = $this->pageFile($pageId, $workspace);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Page JSON konnte nicht erzeugt werden: ' . json_last_error_msg());
        }

        $bytes = file_put_contents($file, $json, LOCK_EX);

        if ($bytes === false) {
            throw new \RuntimeException('Page JSON konnte nicht geschrieben werden: ' . $file);
        }

        clearstatcache(true, $file);

        $verify = json_decode((string)file_get_contents($file), true);

        if (!is_array($verify)) {
            throw new \RuntimeException('Speicherprüfung fehlgeschlagen: Datei enthält kein gültiges JSON.');
        }
    }


    protected function updateNode(array &$data, array $payload): array
    {
        $nodeId = (string)($payload['node_id'] ?? '');

        if ($nodeId === '') {
            throw new \RuntimeException('Node-ID fehlt.');
        }

        $base = (array)($payload['base'] ?? []);
        $properties = (array)($payload['properties'] ?? []);

        if (!$this->updateNodeInTree($data, $nodeId, $base, $properties)) {
            throw new \RuntimeException('Node nicht gefunden: ' . $nodeId);
        }

        return [
            'node_id' => $nodeId,
        ];
    }

    protected function updateNodeInTree(array &$node, string $nodeId, array $base, array $properties): bool
    {
        if ((string)($node['id'] ?? '') === $nodeId) {
            foreach (['title', 'status', 'visibility', 'editor_note'] as $key) {
                if (array_key_exists($key, $base)) {
                    $node[$key] = $base[$key];
                }
            }

            $node['properties'] = $this->mergeProperties(
                (array)($node['properties'] ?? []),
                $properties
            );

            $this->syncLegacyFields($node);

            $this->syncCodeBlockNode($node);
            $node['updated_at'] = date('c');

            return true;
        }

        if (!isset($node['children']) || !is_array($node['children'])) {
            return false;
        }

        foreach ($node['children'] as &$child) {
            if (!is_array($child)) {
                continue;
            }

            if ($this->updateNodeInTree($child, $nodeId, $base, $properties)) {
                unset($child);
                return true;
            }
        }

        unset($child);

        return false;
    }

    protected function syncCodeBlockNode(array &$node): void
    {
        $type = strtolower((string)($node['type'] ?? ''));

        if (!str_contains($type, 'codeblock') && !str_contains($type, 'codesnippet')) {
            return;
        }

        $content = (array)($node['properties']['content'] ?? []);

        if (array_key_exists('code', $content)) {
            $node['code'] = (string)$content['code'];
            $node['content'] = (string)$content['code'];
        }

        if (array_key_exists('language', $content)) {
            $node['language'] = $this->sanitizeCodeLanguage((string)$content['language']);
            $node['properties']['content']['language'] = $node['language'];
        }

        if (array_key_exists('caption', $content)) {
            $node['caption'] = (string)$content['caption'];
        }
    }

    protected function sanitizeCodeLanguage(string $language): string
    {
        $language = strtolower(trim($language));
        $language = preg_replace('/[^a-z0-9_+#.-]/', '', $language) ?: 'plaintext';

        return $language !== '' ? $language : 'plaintext';
    }

    protected function mergeProperties(array $current, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value) && isset($current[$key]) && is_array($current[$key])) {
                $current[$key] = $this->mergeProperties($current[$key], $value);
            } else {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    protected function addNode(array &$data, array $payload): array
    {
        $parentId = (string)($payload['parent_id'] ?? $data['id'] ?? '');
        $type = (string)($payload['type'] ?? '');

        if ($type === '') {
            throw new \RuntimeException('Node-Typ fehlt.');
        }

        $node = $this->createNodeFromType($type, (array)($payload['defaults'] ?? []));

        if (!$this->appendChildToNode($data, $parentId, $node)) {
            throw new \RuntimeException('Parent-Node nicht gefunden: ' . $parentId);
        }

        return [
            'node_id' => $node['id'],
            'parent_id' => $parentId,
            'type' => $node['type'],
        ];
    }

    protected function deleteNode(array &$data, array $payload): array
    {
        $nodeId = (string)($payload['node_id'] ?? '');
        if ($nodeId === '' || $nodeId === (string)($data['id'] ?? '')) {
            throw new \RuntimeException('RootNode kann nicht gelöscht werden.');
        }
        if (!$this->removeNode($data, $nodeId)) {
            throw new \RuntimeException('Node nicht gefunden: ' . $nodeId);
        }
        return ['node_id' => $nodeId];
    }

    protected function duplicateNode(array &$data, array $payload): array
    {
        $nodeId = (string)($payload['node_id'] ?? '');
        if ($nodeId === '') {
            throw new \RuntimeException('Node-ID fehlt.');
        }
        $node = $this->findNode($data, $nodeId);
        if (!$node) {
            throw new \RuntimeException('Node nicht gefunden: ' . $nodeId);
        }
        $copy = $this->deepCopyNode($node);
        if (!$this->insertAfterSibling($data, $nodeId, $copy)) {
            throw new \RuntimeException('Kopie konnte nicht eingefügt werden.');
        }
        return ['source_node_id' => $nodeId, 'node_id' => $copy['id']];
    }

    protected function pasteCopy(array &$data, array $payload): array
    {
        $parentId = (string)($payload['parent_id'] ?? '');
        $node = (array)($payload['node'] ?? []);

        if ($parentId === '') {
            throw new \RuntimeException('Parent-ID fehlt.');
        }

        if ($node === []) {
            throw new \RuntimeException('Clipboard-Node fehlt.');
        }

        $copy = $this->deepCopyNode($node);

        if (!$this->appendChildToNode($data, $parentId, $copy)) {
            throw new \RuntimeException('Parent-Node nicht gefunden: ' . $parentId);
        }

        return [
            'source_node_id' => (string)($node['id'] ?? ''),
            'node_id' => $copy['id'],
            'parent_id' => $parentId,
        ];
    }

    protected function pasteReference(array &$data, array $payload): array
    {
        $parentId = (string)($payload['parent_id'] ?? '');
        $sourceNodeId = (string)($payload['source_node_id'] ?? ($payload['node']['id'] ?? ''));

        if ($parentId === '') {
            throw new \RuntimeException('Parent-ID fehlt.');
        }

        if ($sourceNodeId === '') {
            throw new \RuntimeException('Source-Node-ID fehlt.');
        }

        $reference = [
            'id' => $this->newNodeId('ref'),
            'type' => 'ReferenceNode',
            'title' => 'Referenz: ' . $sourceNodeId,
            'source_node_id' => $sourceNodeId,
            'mode' => 'live',
            'children' => [],
        ];

        if (!$this->appendChildToNode($data, $parentId, $reference)) {
            throw new \RuntimeException('Parent-Node nicht gefunden: ' . $parentId);
        }

        return [
            'node_id' => $reference['id'],
            'source_node_id' => $sourceNodeId,
            'parent_id' => $parentId,
        ];
    }

    protected function createNodeFromType(string $type, array $overrides = []): array
    {
        $type = $this->normalizeNodeType($type);
        $node = $this->registry->defaults($type);
        foreach ($overrides as $key => $value) {
            if ($key !== 'id') {
                $node[$key] = $value;
            }
        }
        $node['type'] = $type;
        $node['id'] = $this->newNodeId($this->slug((string)($node['title'] ?? $type)));
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $index => $child) {
                if (is_array($child)) {
                    $node['children'][$index] = $this->normalizeNewChild($child);
                }
            }
        }
        return $node;
    }


    protected function appendChildToNode(array &$node, string $parentId, array $childNode): bool
    {
        if ((string)($node['id'] ?? '') === $parentId) {
            $this->assertCanContain((string)($node['type'] ?? 'Node'), (string)($childNode['type'] ?? 'Node'));

            if (!isset($node['children']) || !is_array($node['children'])) {
                $node['children'] = [];
            }

            $node['children'][] = $childNode;

            return true;
        }

        if (!isset($node['children']) || !is_array($node['children'])) {
            return false;
        }

        foreach ($node['children'] as &$child) {
            if (!is_array($child)) {
                continue;
            }

            if ($this->appendChildToNode($child, $parentId, $childNode)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeNewChild(array $child): array
    {
        $type = (string)($child['type'] ?? 'Node');
        $title = (string)($child['title'] ?? $type);
        $child['id'] = $this->newNodeId($this->slug($title));
        $child['type'] = $type;
        $child['title'] = $title;
        $child['children'] ??= [];
        foreach ((array)$child['children'] as $index => $grandChild) {
            if (is_array($grandChild)) {
                $child['children'][$index] = $this->normalizeNewChild($grandChild);
            }
        }
        return $child;
    }

    protected function deepCopyNode(array $node): array
    {
        $node = $this->normalizeNewChild($node);
        $node['title'] = (string)($node['title'] ?? $node['type'] ?? 'Node') . ' Kopie';
        return $node;
    }

    protected function syncLegacyFields(array &$node): void
    {
        $type = $this->normalizeNodeType((string)($node['type'] ?? 'Node'));

        if (!isset($node['properties']) || !is_array($node['properties'])) {
            $node['properties'] = [];
        }

        foreach (['content', 'layout', 'spacing', 'design', 'behavior', 'advanced'] as $group) {
            if (!isset($node['properties'][$group]) || !is_array($node['properties'][$group])) {
                $node['properties'][$group] = [];
            }
        }

        $props =& $node['properties'];
        $content =& $props['content'];
        $layout =& $props['layout'];
        $spacing =& $props['spacing'];
        $behavior =& $props['behavior'];
        $advanced =& $props['advanced'];

        switch ($type) {
            case 'TextNode':
                $value = null;
                if (array_key_exists('text', $content)) {
                    $value = $content['text'];
                } elseif (array_key_exists('content', $content)) {
                    $value = $content['content'];
                }

                if ($value !== null) {
                    $content['text'] = $value;
                    $content['content'] = $value;
                    $node['content'] = $value;
                }
                break;

            case 'MarkdownNode':
                $value = null;
                if (array_key_exists('markdown', $content)) {
                    $value = $content['markdown'];
                } elseif (array_key_exists('content', $content)) {
                    $value = $content['content'];
                }

                if ($value !== null) {
                    $content['markdown'] = $value;
                    $content['content'] = $value;
                    $node['markdown'] = $value;
                    $node['content'] = $value;
                }
                break;

            case 'CssNode':
                $value = null;
                if (array_key_exists('css', $content)) {
                    $value = $content['css'];
                } elseif (array_key_exists('content', $content)) {
                    $value = $content['content'];
                }

                if ($value !== null) {
                    $content['css'] = $value;
                    $content['content'] = $value;
                    $node['css'] = $value;
                    $node['content'] = $value;
                }
                break;

            case 'HtmlNode':
                $value = null;
                if (array_key_exists('html', $content)) {
                    $value = $content['html'];
                } elseif (array_key_exists('content', $content)) {
                    $value = $content['content'];
                }

                if ($value !== null) {
                    $content['html'] = $value;
                    $content['content'] = $value;
                    $node['html'] = $value;
                    $node['content'] = $value;
                }
                break;

            case 'ImageNode':
                $src = null;
                if (array_key_exists('media_id', $content)) {
                    $src = $content['media_id'];
                } elseif (array_key_exists('src', $content)) {
                    $src = $content['src'];
                }

                if ($src !== null) {
                    $content['media_id'] = $src;
                    $content['src'] = $src;
                    $node['src'] = $src;
                }
                if (array_key_exists('alt', $content)) {
                    $node['alt'] = $content['alt'];
                }
                if (array_key_exists('caption', $content)) {
                    $node['caption'] = $content['caption'];
                }
                if (array_key_exists('url', $behavior)) {
                    $node['url'] = $behavior['url'];
                }
                if (array_key_exists('target', $behavior)) {
                    $node['target'] = $behavior['target'];
                }
                break;

            case 'ButtonNode':
                if (array_key_exists('label', $content)) {
                    $node['label'] = $content['label'];
                }
                if (array_key_exists('url', $behavior)) {
                    $node['url'] = $behavior['url'];
                }
                if (array_key_exists('target', $behavior)) {
                    $node['target'] = $behavior['target'];
                }
                break;

            case 'ColumnsNode':
                if (array_key_exists('columns', $layout)) {
                    $node['columns'] = $layout['columns'];
                    $advanced['settings']['columns'] = $layout['columns'];
                } elseif (isset($advanced['settings']['columns'])) {
                    $node['columns'] = $advanced['settings']['columns'];
                    $layout['columns'] = $advanced['settings']['columns'];
                }

                if (array_key_exists('gap', $spacing)) {
                    $node['gap'] = $spacing['gap'];
                    $advanced['settings']['gap'] = $spacing['gap'];
                } elseif (isset($advanced['settings']['gap'])) {
                    $node['gap'] = $advanced['settings']['gap'];
                    $spacing['gap'] = $advanced['settings']['gap'];
                }
                break;
        }
    }
    protected function normalizeNodeType(string $type): string
    {
        $key = strtolower(trim($type));

        return match ($key) {
            'root', 'rootnode' => 'RootNode',
            'page', 'pagenode' => 'RootNode',
            'text', 'textnode' => 'TextNode',
            'heading', 'headingnode', 'headline', 'title', 'titlenode' => 'HeadingNode',
            'codeblock', 'codeblocknode', 'code_highlight', 'codehighlight', 'codesnippet', 'snippet', 'snippetnode' => 'CodeBlockNode',
            'markdown', 'markdownnode' => 'MarkdownNode',
            'html', 'htmlnode' => 'HtmlNode',
            'css', 'cssnode' => 'CssNode',
            'image', 'imagenode', 'bild' => 'ImageNode',
            'button', 'buttonnode' => 'ButtonNode',
            'columns', 'columnsnode' => 'ColumnsNode',
            'column', 'columnnode', 'col' => 'ColumnNode',
            'container', 'containernode' => 'ContainerNode',
            'schedule', 'schedulecontainer', 'schedulecontainernode' => 'ScheduleContainerNode',
            'reference', 'referencenode' => 'ReferenceNode',
            default => $type,
        };
    }

    protected function assertCanContain(string $parentType, string $childType): void
    {
        $originalParentType = $parentType;
        $originalChildType = $childType;
        $parentType = $this->normalizeNodeType($parentType);
        $childType = $this->normalizeNodeType($childType);

        if (!$this->registry->canContain($parentType, $childType)) {
            throw new \RuntimeException($originalParentType . ' darf keine ' . $originalChildType . ' enthalten.');
        }
    }

    protected function &findNodeRef(array &$node, string $id): ?array
    {
        if ((string)($node['id'] ?? '') === $id) {
            return $node;
        }
        foreach ($node['children'] ?? [] as &$child) {
            if (!is_array($child)) {
                continue;
            }
            $found =& $this->findNodeRef($child, $id);
            if ($found !== null) {
                return $found;
            }
        }
        $null = null;
        return $null;
    }

    protected function findNode(array $node, string $id): ?array
    {
        if ((string)($node['id'] ?? '') === $id) {
            return $node;
        }
        foreach ($node['children'] ?? [] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $found = $this->findNode($child, $id);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    protected function removeNode(array &$node, string $id): bool
    {
        if (!isset($node['children']) || !is_array($node['children'])) {
            return false;
        }

        foreach ($node['children'] as $index => &$child) {
            if (!is_array($child)) {
                continue;
            }

            if ((string)($child['id'] ?? '') === $id) {
                array_splice($node['children'], $index, 1);
                unset($child);
                return true;
            }

            if ($this->removeNode($child, $id)) {
                unset($child);
                return true;
            }
        }

        unset($child);

        return false;
    }

    protected function insertAfterSibling(array &$node, string $targetId, array $copy): bool
    {
        if (!isset($node['children']) || !is_array($node['children'])) {
            return false;
        }

        foreach ($node['children'] as $index => &$child) {
            if (!is_array($child)) {
                continue;
            }

            if ((string)($child['id'] ?? '') === $targetId) {
                array_splice($node['children'], $index + 1, 0, [$copy]);
                unset($child);
                return true;
            }

            if ($this->insertAfterSibling($child, $targetId, $copy)) {
                unset($child);
                return true;
            }
        }

        unset($child);

        return false;
    }

    protected function normalizeRoot(array $data, string $pageId): array
    {
        $data['id'] ??= $pageId;
        $data['type'] ??= 'RootNode';
        $data['title'] ??= ucfirst($pageId);
        $data['children'] ??= [];
        return $data;
    }
    protected function normalizeAreaRoot(array $data, string $id): array
    {
        $data['id'] = $this->cleanId((string)($data['id'] ?? $id), $id);
        $data['type'] = (string)($data['type'] ?? 'RootNode');
        if (strtolower($data['type']) === 'area') {
            $data['type'] = 'RootNode';
        }
        $data['kind'] = 'area';
        $data['title'] ??= ucfirst($id);
        $data['visibility'] ??= 'hidden';
        $data['system'] ??= true;
        $data['children'] ??= [];
        return $data;
    }

    protected function areaFile(string $id, string $workspace): string
    {
        return $this->root . '/storage/workspaces/' . $workspace . '/areas/' . $id . '.json';
    }

    protected function cleanTargetKind(string $targetKind): string
    {
        $targetKind = strtolower(trim($targetKind));
        return $targetKind === 'area' ? 'area' : 'page';
    }

    protected function pageFile(string $pageId, string $workspace): string
    {
        return $this->root . '/storage/workspaces/' . $workspace . '/pages/' . $pageId . '.json';
    }

    protected function cleanId(string $id, string $fallback): string
    {
        $id = strtolower($id);
        return preg_replace('/[^a-z0-9_-]/', '', $id) ?: $fallback;
    }

    protected function cleanWorkspace(string $workspace): string
    {
        return in_array($workspace, ['published', 'draft', 'review'], true) ? $workspace : 'draft';
    }

    protected function slug(string $value): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'node';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'node';
    }
    protected function newNodeId(string $prefix = 'n'): string
    {
        return \TreeForge\Core\NodeIdGenerator::generateFromIds($this->activeNodeIds);
    }
}