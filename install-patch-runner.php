<?php
declare(strict_types=1);

$root = __DIR__;

function writeFileSafe(string $file, string $content): void
{
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }

    if (file_exists($file)) {
        copy($file, $file . '.bak-' . date('Ymd-His'));
        echo "Backup erstellt: {$file}\n";
    }

    file_put_contents($file, $content);
    echo "Datei geschrieben: {$file}\n";
}

writeFileSafe($root . '/patches/run.php', <<<'PHP'
<?php
declare(strict_types=1);

$patchDir = __DIR__;
$root = dirname(__DIR__);
$executedFile = $patchDir . '/executed.json';
$logFile = $root . '/storage/logs/patch-runner.log';

function runnerLog(string $message): void
{
    global $logFile;

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;

    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0775, true);
    }

    file_put_contents($logFile, $line, FILE_APPEND);
}

function loadExecuted(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveExecuted(string $file, array $data): void
{
    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

$executed = loadExecuted($executedFile);
$patches = glob($patchDir . '/patch-*.php') ?: [];
sort($patches);

runnerLog('Patch Runner gestartet');

foreach ($patches as $patchFile) {
    $patchName = basename($patchFile);

    if (isset($executed[$patchName])) {
        runnerLog("Übersprungen: {$patchName}");
        continue;
    }

    runnerLog("Starte: {$patchName}");

    $patch = require $patchFile;

    if (!is_callable($patch)) {
        runnerLog("FEHLER: {$patchName} gibt keine Funktion zurück");
        exit(1);
    }

    try {
        $patch($root, 'runnerLog');

        $executed[$patchName] = [
            'executed_at' => date('c'),
            'status' => 'ok'
        ];

        saveExecuted($executedFile, $executed);
        runnerLog("Fertig: {$patchName}");
    } catch (Throwable $e) {
        runnerLog("FEHLER in {$patchName}: " . $e->getMessage());
        exit(1);
    }
}

runnerLog('Patch Runner beendet');
PHP);

writeFileSafe($root . '/public/patches/index.php', <<<'PHP'
<?php
declare(strict_types=1);

echo '<pre>';
require_once dirname(__DIR__, 2) . '/patches/run.php';
echo '</pre>';
PHP);

writeFileSafe($root . '/patches/patch-003-image-node.php', <<<'PHP'
<?php
declare(strict_types=1);

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

    $log('Patch 003 ImageNode gestartet');

    $write($root . '/app/Core/ImageNode.php', <<<'CODE'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

class ImageNode extends Node
{
    public function src(): string
    {
        return (string)($this->data['src'] ?? '');
    }

    public function alt(): string
    {
        return (string)($this->data['alt'] ?? '');
    }

    public function caption(): string
    {
        return (string)($this->data['caption'] ?? '');
    }
}
CODE);

    $write($root . '/app/Core/NodeFactory.php', <<<'CODE'
<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class NodeFactory
{
    public static function create(array $data): Node
    {
        return match ($data['type'] ?? '') {
            'text'  => new TextNode($data),
            'image' => new ImageNode($data),

            default => throw new RuntimeException(
                'Unknown node type: ' . ($data['type'] ?? 'undefined')
            ),
        };
    }
}
CODE);

    $write($root . '/storage/pages/home.json', <<<'CODE'
{
  "id": "home",
  "type": "page",
  "title": "Startseite",
  "children": [
    {
      "id": "node_hero",
      "type": "text",
      "content": "Welcome to TreeForge CMS"
    },
    {
      "id": "node_image_demo",
      "type": "image",
      "src": "/assets/img/treeforge-demo.svg",
      "alt": "TreeForge Demo Icon",
      "caption": "ImageNode rendered from JSON."
    }
  ]
}
CODE);

    $write($root . '/public/assets/img/treeforge-demo.svg', <<<'CODE'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 220">
  <circle cx="110" cy="110" r="96" fill="#F5F3EA" stroke="#173F35" stroke-width="8"/>
  <path d="M72 145h76l-12 22H84z" fill="#121A17"/>
  <path d="M70 130h80v18H70z" fill="#121A17"/>
  <path d="M110 70v62" stroke="#121A17" stroke-width="10" stroke-linecap="round"/>
  <path d="M110 98L82 82" stroke="#121A17" stroke-width="8" stroke-linecap="round"/>
  <path d="M110 98l28-16" stroke="#121A17" stroke-width="8" stroke-linecap="round"/>
  <circle cx="110" cy="48" r="12" fill="#4F8F46"/>
  <circle cx="82" cy="74" r="12" fill="#4F8F46"/>
  <circle cx="138" cy="74" r="12" fill="#4F8F46"/>
  <circle cx="70" cy="104" r="12" fill="#4F8F46"/>
  <circle cx="150" cy="104" r="12" fill="#4F8F46"/>
</svg>
CODE);

    $log('Patch 003 ImageNode fertig');
};
PHP);

echo "\nPatch-Runner installiert.\n";
echo "Ausführen CLI:\n";
echo "php patches\\run.php\n\n";
echo "Oder Browser:\n";
echo "http://treeforge.test/patches/\n";