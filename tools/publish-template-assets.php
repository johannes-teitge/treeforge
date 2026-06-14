<?php
declare(strict_types=1);

use TreeForge\Core\Templates\TemplateAssetPublisher;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$publisherFile = $root . '/app/Core/Templates/TemplateAssetPublisher.php';
if (!class_exists(TemplateAssetPublisher::class) && file_exists($publisherFile)) {
    require_once $publisherFile;
}

if (!class_exists(TemplateAssetPublisher::class)) {
    fwrite(STDERR, "TemplateAssetPublisher nicht gefunden.\n");
    exit(1);
}

$template = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--template=')) {
        $template = substr($arg, strlen('--template='));
    }
}

$publisher = new TemplateAssetPublisher($root);
$urls = $publisher->publishCoreCss();

echo "Core CSS:\n";
foreach ($urls as $url) {
    echo "- {$url}\n";
}

if ($template !== null && $template !== '') {
    $templateUrls = $publisher->publishTemplateCss($template);
    echo "\nTemplate CSS ({$template}):\n";
    foreach ($templateUrls as $url) {
        echo "- {$url}\n";
    }
}