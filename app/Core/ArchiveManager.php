<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class ArchiveManager
{
    protected string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    public function archiveRoot(): string
    {
        return $this->root . '/storage/workspaces/archive';
    }

    public function pageArchivePath(string $pageId): string
    {
        return $this->archiveRoot() . '/pages/' . $pageId;
    }

    public function archiveCurrentPublished(string $pageId): ?string
    {
        $publishedFile = Workspace::published($this->root)->pagePath($pageId);

        if (!file_exists($publishedFile)) {
            return null;
        }

        $version = date('Y-m-d-His');
        $target = $this->pageArchivePath($pageId) . '/' . $version . '.json';

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        copy($publishedFile, $target);

        return $version;
    }

    public function getVersions(string $pageId): array
    {
        $versions = [];

        $newPath = $this->pageArchivePath($pageId);

        foreach (glob($newPath . '/*.json') ?: [] as $file) {
            $version = basename($file, '.json');

            $versions[] = [
                'version' => $version,
                'file' => $file,
                'format' => 'page-archive',
                'created_at' => $this->versionToDate($version),
            ];
        }

        // Alte Archivstruktur aus früheren Patches unterstützen:
        // archive/YYYY-MM-DD-HHMMSS/home.json
        foreach (glob($this->archiveRoot() . '/*/' . $pageId . '.json') ?: [] as $file) {
            $dir = basename(dirname($file));

            if ($dir === 'pages') {
                continue;
            }

            $alreadyExists = false;

            foreach ($versions as $version) {
                if ($version['version'] === $dir) {
                    $alreadyExists = true;
                    break;
                }
            }

            if (!$alreadyExists) {
                $versions[] = [
                    'version' => $dir,
                    'file' => $file,
                    'format' => 'legacy-folder',
                    'created_at' => $this->versionToDate($dir),
                ];
            }
        }

        usort($versions, static function (array $a, array $b): int {
            return strcmp($b['version'], $a['version']);
        });

        return $versions;
    }

    public function loadVersion(string $pageId, string $version): array
    {
        $file = $this->findVersionFile($pageId, $version);

        if ($file === null) {
            throw new RuntimeException("Archive version not found: {$pageId} / {$version}");
        }

        $data = json_decode((string)file_get_contents($file), true);

        if (!is_array($data)) {
            throw new RuntimeException("Invalid archive JSON: {$version}");
        }

        return $data;
    }

    public function restoreVersion(string $pageId, string $version): void
    {
        $data = $this->loadVersion($pageId, $version);

        $this->archiveCurrentPublished($pageId);

        $data['_workflow'] = [
            'status' => 'restored_from_archive',
            'from' => 'archive',
            'version' => $version,
            'to' => Workspace::PUBLISHED,
            'created_at' => date('c'),
        ];

        Workspace::published($this->root)->savePage($pageId, $data);
    }

    protected function findVersionFile(string $pageId, string $version): ?string
    {
        $newFile = $this->pageArchivePath($pageId) . '/' . $version . '.json';

        if (file_exists($newFile)) {
            return $newFile;
        }

        $legacyFile = $this->archiveRoot() . '/' . $version . '/' . $pageId . '.json';

        if (file_exists($legacyFile)) {
            return $legacyFile;
        }

        return null;
    }

    protected function versionToDate(string $version): string
    {
        $date = \DateTime::createFromFormat('Y-m-d-His', $version);

        if ($date instanceof \DateTime) {
            return $date->format('d.m.Y H:i:s');
        }

        return $version;
    }
}