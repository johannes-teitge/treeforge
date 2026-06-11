<?php
declare(strict_types=1);

namespace App\TreeForge;

final class NodeManifest
{
    public static function load(string $file): array
    {
        if (!file_exists($file)) {
            throw new \RuntimeException('Node manifest not found: ' . $file);
        }

        $json = file_get_contents($file);
        $data = json_decode((string)$json, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid node manifest: ' . $file);
        }

        foreach (['type', 'class', 'file', 'label'] as $required) {
            if (empty($data[$required])) {
                throw new \RuntimeException('Missing manifest field "' . $required . '" in ' . $file);
            }
        }

        $data['_manifest_file'] = $file;
        $data['_base_path'] = dirname($file);

        return $data;
    }
}