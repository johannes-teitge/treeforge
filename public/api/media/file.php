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