<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

$workspace = null;
$page = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--workspace=')) {
        $workspace = trim(substr($arg, 12));
    }
    if (str_starts_with($arg, '--page=')) {
        $page = trim(substr($arg, 7));
    }
}

function outLine(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function readJson(string $file): ?array
{
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function writeJson(string $file, array $data): void
{
    copy($file, $file . '.bak-' . date('Ymd-His'));
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        LOCK_EX
    );
}

function canonicalType(string $type): string
{
    $key = strtolower(trim($type));
    $key = str_replace(['-', ' '], '_', $key);

    return match ($key) {
        'textnode', 'text_node', 'richtext', 'richtextnode' => 'text',
        'headingnode', 'heading_node', 'headline', 'headlinenode', 'title', 'titlenode' => 'heading',
        'htmlnode', 'html_node', 'rawhtml', 'raw_html', 'rawhtmlnode', 'raw_html_node' => 'html',
        'javascriptnode', 'java_script_node', 'js', 'jsnode', 'js_node', 'script', 'scriptnode', 'script_node' => 'javascript',
        'imagenode', 'image_node', 'picture', 'picturenode' => 'image',
        'buttonnode', 'button_node' => 'button',
        'columnsnode', 'columns_node' => 'columns',
        'columnnode', 'column_node' => 'column',
        'cssnode', 'css_node', 'stylenode', 'style_node' => 'css',
        'markdownnode', 'markdown_node', 'md', 'mdnode' => 'markdown',
        'codeblocknode', 'code_block_node', 'codehighlight', 'codehighlighter', 'codesnippet', 'snippet' => 'codeblock',
        default => $type,
    };
}

function normalizeNodeTypes(array &$node, int &$changed): void
{
    $type = (string)($node['type'] ?? '');

    if ($type !== '' && $type !== 'page') {
        $canonical = canonicalType($type);
        if ($canonical !== $type) {
            $node['type'] = $canonical;
            $changed++;
        }
    }

    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) {
            if (is_array($child)) {
                normalizeNodeTypes($child, $changed);
            }
        }
        unset($child);
    }
}

$files = [];

if ($workspace !== null && $workspace !== '') {
    $pattern = $root . '/storage/workspaces/' . $workspace . '/pages/*.json';
    $files = glob($pattern) ?: [];
} else {
    $patterns = [
        $root . '/storage/workspaces/*/pages/*.json',
        $root . '/storage/pages/*.json',
    ];

    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: [] as $file) {
            $files[] = $file;
        }
    }
}

$files = array_values(array_unique($files));
sort($files);

if ($page !== null && $page !== '') {
    $files = array_values(array_filter($files, static function (string $file) use ($page): bool {
        return basename($file) === $page . '.json';
    }));
}

outLine('TreeForge Node-Type Normalizer gestartet' . ($dryRun ? ' (DRY RUN)' : ''));
outLine('Gefundene Page-Dateien: ' . count($files));

$totalFiles = 0;
$totalChanges = 0;

foreach ($files as $file) {
    $data = readJson($file);
    if ($data === null) {
        outLine('Übersprungen, ungültiges JSON: ' . $file);
        continue;
    }

    $changed = 0;
    normalizeNodeTypes($data, $changed);

    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    $relative = str_replace('\\', '/', $relative);

    if ($changed === 0) {
        outLine('OK, keine Änderung nötig: ' . $relative);
        continue;
    }

    $totalFiles++;
    $totalChanges += $changed;
    outLine('Änderungen: ' . $relative . ' | Typen: ' . $changed);

    if (!$dryRun) {
        writeJson($file, $data);
    }
}

outLine('Fertig. Dateien geändert: ' . $totalFiles . ', Typen geändert: ' . $totalChanges);