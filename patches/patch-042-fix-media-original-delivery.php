<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 042
 * Fix Media Original Delivery
 *
 * Problem:
 * - MediaManager erzeugt URLs wie /media/originals/datei.jpg
 * - Die Originale liegen aber unter storage/media/originals/
 * - Dadurch sind sie nicht automatisch öffentlich erreichbar.
 *
 * Fix:
 * - sicherer Media-File-Endpunkt: /api/media/file.php?path=...
 * - MediaConfig::publicOriginalUrl() nutzt diesen Endpunkt
 * - vorhandene Media-Ansicht zeigt Bilder korrekt an
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

    $log('Patch 042 Fix Media Original Delivery gestartet');

    $configFile = $root . '/app/Modules/Media/MediaConfig.php';

    if (file_exists($configFile)) {
        $config = file_get_contents($configFile);

        $old = <<<'PHP'
    public function publicOriginalUrl(string $relativePath): string
    {
        return '/media/originals/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }
PHP;

        $new = <<<'PHP'
    public function publicOriginalUrl(string $relativePath): string
    {
        return '/api/media/file.php?path=' . rawurlencode(ltrim(str_replace('\\', '/', $relativePath), '/'));
    }
PHP;

        if (str_contains($config, $old)) {
            $config = str_replace($old, $new, $config);
            $write($configFile, $config);
        } else {
            $log('Hinweis: publicOriginalUrl Zielblock nicht gefunden oder bereits geändert');
        }
    }

    $write($root . '/public/api/media/file.php', <<<'PHP'
<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$baseDir = realpath($root . '/storage/media/originals');

if ($baseDir === false) {
    http_response_code(404);
    exit('Media directory not found.');
}

$path = (string)($_GET['path'] ?? '');
$path = str_replace('\\', '/', $path);
$path = ltrim($path, '/');

if ($path === '' || str_contains($path, '..')) {
    http_response_code(400);
    exit('Invalid media path.');
}

$file = realpath($baseDir . '/' . $path);

if ($file === false || !is_file($file) || !str_starts_with($file, $baseDir)) {
    http_response_code(404);
    exit('Media file not found.');
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

if (!in_array($extension, $allowed, true)) {
    http_response_code(403);
    exit('Media type not allowed.');
}

$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
];

$mime = $mimeMap[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

readfile($file);
PHP);

    $write($root . '/docs/treeforge/32-fix-media-original-delivery.md', <<<'MD'
# Fix Media Original Delivery

Patch 042 behebt die Anzeige von Originalbildern in der Medienbibliothek.

## Problem

Die Medien liegen unter:

```text
storage/media/originals/
```

Die bisherige URL zeigte aber auf:

```text
/media/originals/...
```

Dieser Pfad ist nicht automatisch öffentlich erreichbar.

## Lösung

Ein sicherer File-Endpunkt liefert Originale aus:

```text
/api/media/file.php?path=landschaft.jpg
```

`MediaConfig::publicOriginalUrl()` erzeugt nun diese URL.

## Sicherheit

Der Endpunkt prüft:

- kein leerer Pfad
- kein `..`
- Datei muss innerhalb von `storage/media/originals` liegen
- nur erlaubte Bildtypen
- Content-Type wird passend gesetzt

## Später

Für Produktion kann man später alternativ einen Symlink oder Rewrite für Medien verwenden.
MD);

    $log('Patch 042 Fix Media Original Delivery fertig');
};
