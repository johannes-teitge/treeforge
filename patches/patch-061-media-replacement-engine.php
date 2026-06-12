<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 061
 * Media Replacement Engine
 *
 * Ziel:
 * - Datei-Ersetzen wirklich stabil machen
 * - alte Datei sichern
 * - bestehende Originaldatei überschreiben
 * - Media-ID und relative_path bleiben gleich
 * - Ziel-Dateiname bleibt gleich
 * - original_name wird auf neue Upload-Datei gesetzt
 * - Breite/Höhe/Größe/MIME werden aktualisiert
 * - versions[] wird sauber geschrieben
 * - Cache-Invalidierung vorbereitet
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

    $log('Patch 061 Media Replacement Engine gestartet');

    $write($root . '/app/Modules/Media/MediaReplaceService.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

use RuntimeException;
use TreeForge\Core\Settings\SettingsManager;

class MediaReplaceService
{
    protected MediaConfig $config;
    protected MediaRepository $repo;
    protected array $settings;

    public function __construct(
        protected string $root
    ) {
        $this->config = new MediaConfig($root);
        $this->repo = new MediaRepository($root);

        $settings = new SettingsManager($root);
        $this->settings = (array)($settings->all()['media'] ?? []);
    }

    public function replace(string $id, array $file): array
    {
        if (empty($this->settings['replace']['enabled'])) {
            throw new RuntimeException('Dateien ersetzen ist deaktiviert.');
        }

        $item = $this->repo->findById($id);

        if (!$item) {
            throw new RuntimeException('Medium nicht gefunden.');
        }

        $this->assertUploadOk($file);

        $newOriginalName = (string)($file['name'] ?? '');
        $tmpName = (string)($file['tmp_name'] ?? '');
        $newSize = (int)($file['size'] ?? 0);

        $relativePath = ltrim(str_replace('\\', '/', (string)($item['relative_path'] ?? '')), '/');

        if ($relativePath === '') {
            throw new RuntimeException('relative_path fehlt.');
        }

        $currentExtension = strtolower((string)($item['extension'] ?? pathinfo((string)($item['filename'] ?? $relativePath), PATHINFO_EXTENSION)));
        $newExtension = strtolower(pathinfo($newOriginalName, PATHINFO_EXTENSION));

        if ($currentExtension === '' || $newExtension === '') {
            throw new RuntimeException('Dateierweiterung fehlt.');
        }

        if ($currentExtension !== $newExtension) {
            throw new RuntimeException('Formatwechsel ist aktuell nicht erlaubt. Erwartet: .' . $currentExtension . ', erhalten: .' . $newExtension);
        }

        $this->assertSize($newSize, $currentExtension);
        $this->assertSecurity($newOriginalName, $newExtension);

        $target = $this->absoluteOriginalPath($relativePath);

        if (!is_file($target)) {
            throw new RuntimeException('Originaldatei nicht gefunden: ' . $relativePath);
        }

        $backup = $this->backupOriginal($target, $relativePath, $item, $newOriginalName);

        if (!@copy($tmpName, $target)) {
            throw new RuntimeException('Neue Datei konnte nicht über die Originaldatei geschrieben werden.');
        }

        @unlink($tmpName);
        clearstatcache(true, $target);

        $updated = $this->refreshFileInfo($item, $target, $newOriginalName);
        $updated['versions'] = $this->appendVersion((array)($item['versions'] ?? []), $backup);
        $updated['replaced_at'] = date('c');
        $updated['updated_at'] = date('c');

        $this->invalidateCache($relativePath);
        $this->repo->save($updated);

        return $this->repo->findById($id) ?? $updated;
    }

    protected function assertUploadOk(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $name = (string)($file['name'] ?? '');

        if ($name === '' || $tmpName === '') {
            throw new RuntimeException('Ungültiger Upload.');
        }

        if (!is_uploaded_file($tmpName) && !is_file($tmpName)) {
            throw new RuntimeException('Temporäre Upload-Datei wurde nicht gefunden.');
        }
    }

    protected function absoluteOriginalPath(string $relativePath): string
    {
        $base = rtrim($this->config->originalsDir(), '/\\');
        $path = $base . '/' . ltrim($relativePath, '/');

        $realBase = realpath($base);
        $realDir = realpath(dirname($path));

        if ($realBase === false || $realDir === false || !str_starts_with(str_replace('\\', '/', $realDir), str_replace('\\', '/', $realBase))) {
            throw new RuntimeException('Ungültiger Medienpfad.');
        }

        return $path;
    }

    protected function backupOriginal(string $target, string $relativePath, array $item, string $newOriginalName): array
    {
        $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $baseName = pathinfo($target, PATHINFO_FILENAME);
        $relativeDir = trim(dirname($relativePath), '/.');

        $versionDir = $this->root . '/storage/media/versions'
            . ($relativeDir !== '' ? '/' . $relativeDir : '');

        if (!is_dir($versionDir)) {
            mkdir($versionDir, 0775, true);
        }

        $versionFilename = $baseName . '_' . date('Ymd_His') . '.' . $extension;
        $versionTarget = $versionDir . '/' . $versionFilename;

        if (!copy($target, $versionTarget)) {
            throw new RuntimeException('Alte Version konnte nicht gesichert werden.');
        }

        return [
            'file' => ltrim(str_replace('\\', '/', substr($versionTarget, strlen($this->root))), '/'),
            'original_name' => (string)($item['original_name'] ?? $item['filename'] ?? basename($relativePath)),
            'replaced_by' => $newOriginalName,
            'size' => (int)($item['size'] ?? filesize($target) ?: 0),
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'mime' => (string)($item['mime'] ?? ''),
            'created_at' => date('c'),
        ];
    }

    protected function appendVersion(array $versions, array $backup): array
    {
        $versions[] = $backup;

        $maxVersions = max(1, (int)($this->settings['replace']['max_versions'] ?? 10));

        while (count($versions) > $maxVersions) {
            $old = array_shift($versions);
            $oldFile = $this->root . '/' . ltrim((string)($old['file'] ?? ''), '/\\');

            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        return $versions;
    }

    protected function refreshFileInfo(array $item, string $target, string $newOriginalName): array
    {
        $imageSize = @getimagesize($target);
        $mime = function_exists('mime_content_type') ? (string)mime_content_type($target) : (string)($item['mime'] ?? '');

        $item['original_name'] = $newOriginalName;
        $item['mime'] = $mime;
        $item['size'] = filesize($target) ?: 0;

        if (is_array($imageSize)) {
            $item['width'] = $imageSize[0] ?? null;
            $item['height'] = $imageSize[1] ?? null;
        } else {
            $item['width'] = null;
            $item['height'] = null;
        }

        return $item;
    }

    protected function invalidateCache(string $relativePath): void
    {
        if (empty($this->settings['replace']['invalidate_cache_on_replace'])) {
            return;
        }

        $cacheDir = (string)($this->settings['render_cache']['cache_dir'] ?? 'storage/media/cache');
        $cacheBase = $this->root . '/' . trim($cacheDir, '/\\');

        if (!is_dir($cacheBase)) {
            return;
        }

        $baseName = pathinfo($relativePath, PATHINFO_FILENAME);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheBase, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (str_contains($file->getFilename(), $baseName)) {
                @unlink($file->getPathname());
            }
        }
    }

    protected function assertSize(int $size, string $extension): void
    {
        $maxMb = (int)($this->settings['max_file_size_mb'] ?? 10);

        if ($extension === 'zip') {
            $maxMb = (int)($this->settings['zip']['max_size_mb'] ?? $maxMb);
        }

        $maxBytes = $maxMb * 1024 * 1024;

        if ($size > $maxBytes) {
            throw new RuntimeException('Datei ist zu groß. Maximal erlaubt: ' . $maxMb . ' MB.');
        }
    }

    protected function assertSecurity(string $filename, string $extension): void
    {
        if (!empty($this->settings['security']['block_php_files'])) {
            $blocked = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8'];

            if (in_array($extension, $blocked, true)) {
                throw new RuntimeException('Ausführbare PHP-Dateien sind blockiert.');
            }
        }

        if (!empty($this->settings['security']['block_double_extensions'])) {
            if (preg_match('/\.(php|phtml|phar|exe|bat|cmd|sh|js)\./i', $filename)) {
                throw new RuntimeException('Doppelte oder gefährliche Dateiendung blockiert.');
            }
        }
    }

    protected function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet die erlaubte Uploadgröße.',
            UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporärer Upload-Ordner fehlt.',
            UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden.',
            UPLOAD_ERR_EXTENSION => 'Upload wurde durch PHP-Erweiterung gestoppt.',
            default => 'Unbekannter Uploadfehler.',
        };
    }
}
PHP);

    $write($root . '/docs/treeforge/51-media-replacement-engine.md', <<<'MD'
# Media Replacement Engine

Patch 061 stabilisiert das Ersetzen von Medien.

## Verhalten

Beim Ersetzen:

```text
Media-ID bleibt gleich
relative_path bleibt gleich
Ziel-Dateiname bleibt gleich
alte Datei wird gesichert
Originaldatei wird wirklich überschrieben
Meta-Daten werden aktualisiert
versions[] wird geschrieben
Cache wird vorbereitet invalidiert
```

## Speicherort alter Versionen

```text
storage/media/versions/...
```

## Formatwechsel

Noch nicht erlaubt.

```text
webp ersetzt webp
jpg ersetzt jpg
png ersetzt png
svg ersetzt svg
```

Das hält bestehende URLs stabil.
MD);

    $log('Patch 061 Media Replacement Engine fertig');
};
