<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

use RuntimeException;
use TreeForge\Core\Settings\SettingsManager;

class MediaUploadService
{
    protected MediaConfig $config;
    protected MediaMeta $meta;
    protected MediaFilenameService $filenames;
    protected array $settings;

    public function __construct(
        protected string $root
    ) {
        $this->config = new MediaConfig($root);
        $this->meta = new MediaMeta($this->config);
        $this->filenames = new MediaFilenameService();

        $settings = new SettingsManager($root);
        $this->settings = (array)($settings->all()['media'] ?? []);
    }

    public function upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $originalName = (string)($file['name'] ?? '');
        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($originalName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Ungültiger Upload.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '') {
            throw new RuntimeException('Datei ohne Erweiterung ist nicht erlaubt.');
        }

        $this->assertSize($size, $extension);
        $this->assertExtensionAllowed($extension);
        $this->assertSecurity($originalName, $extension);

        $targetDir = $this->targetDir($extension);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $filename = $originalName;

        if (!empty($this->settings['normalize_filenames'])) {
            $filename = $this->filenames->normalize($filename);
        }

        if (!empty($this->settings['unique_filenames'])) {
            $filename = $this->filenames->unique($targetDir, $filename);
        }

        $target = $targetDir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $target)) {
            throw new RuntimeException('Upload konnte nicht gespeichert werden.');
        }

        $relativePath = $this->relativePath($target);
        $fileInfo = $this->fileInfo($target, $relativePath, $originalName);
        $meta = $this->meta->ensure($relativePath, $fileInfo);

        return array_replace_recursive($fileInfo, $meta, [
            'url' => $this->config->publicOriginalUrl($relativePath),
        ]);
    }

    protected function assertSize(int $size, string $extension): void
    {
        $maxMb = (int)($this->settings['max_file_size_mb'] ?? 10);

        if ($this->isZip($extension)) {
            $maxMb = (int)($this->settings['zip']['max_size_mb'] ?? $maxMb);
        }

        if ($this->isDownload($extension)) {
            $maxMb = (int)($this->settings['downloads']['max_size_mb'] ?? $maxMb);
        }

        $maxBytes = $maxMb * 1024 * 1024;

        if ($size > $maxBytes) {
            throw new RuntimeException('Datei ist zu groß. Maximal erlaubt: ' . $maxMb . ' MB.');
        }
    }

    protected function assertExtensionAllowed(string $extension): void
    {
        $types = (array)($this->settings['file_types'] ?? []);
        $allowed = array_unique(array_merge(
            (array)($types['images'] ?? []),
            (array)($types['documents'] ?? []),
            (array)($types['downloads'] ?? []),
            (array)($types['audio'] ?? []),
            (array)($types['video'] ?? [])
        ));

        $allowed = array_map('strtolower', $allowed);

        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Dateityp nicht erlaubt: .' . $extension);
        }

        if ($extension === 'svg' && empty($this->settings['svg']['allow_upload'])) {
            throw new RuntimeException('SVG Upload ist deaktiviert.');
        }

        if ($this->isZip($extension) && empty($this->settings['zip']['allow_upload'])) {
            throw new RuntimeException('ZIP Upload ist deaktiviert.');
        }

        if ($this->isDownload($extension) && empty($this->settings['downloads']['enabled'])) {
            throw new RuntimeException('Downloads sind deaktiviert.');
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

    protected function targetDir(string $extension): string
    {
        $base = rtrim($this->config->originalsDir(), '/\\');

        if ($this->isImage($extension)) {
            return $base . '/' . date('Y/m');
        }

        if ($this->isDocument($extension)) {
            return $base . '/documents/' . date('Y/m');
        }

        if ($this->isDownload($extension)) {
            return $base . '/downloads/' . date('Y/m');
        }

        return $base . '/files/' . date('Y/m');
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = realpath($this->config->originalsDir());

        if ($base === false) {
            return basename($absolutePath);
        }

        return ltrim(str_replace('\\', '/', substr($absolutePath, strlen($base))), '/');
    }

    protected function fileInfo(string $absolutePath, string $relativePath, string $originalName): array
    {
        $mime = function_exists('mime_content_type') ? (string)mime_content_type($absolutePath) : '';
        $size = filesize($absolutePath) ?: 0;
        $width = null;
        $height = null;

        $imageSize = @getimagesize($absolutePath);

        if (is_array($imageSize)) {
            $width = $imageSize[0] ?? null;
            $height = $imageSize[1] ?? null;
        }

        $filename = basename($relativePath);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return [
            'relative_path' => $relativePath,
            'filename' => $filename,
            'original_name' => $originalName,
            'extension' => $extension,
            'kind' => $this->kind($extension),
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'uploaded_at' => date('c'),
        ];
    }

    protected function kind(string $extension): string
    {
        if ($this->isImage($extension)) {
            return $extension === 'svg' ? 'vector' : 'image';
        }

        if ($this->isDocument($extension)) {
            return 'document';
        }

        if ($this->isDownload($extension)) {
            return 'download';
        }

        return 'file';
    }

    protected function isImage(string $extension): bool
    {
        return in_array($extension, (array)($this->settings['file_types']['images'] ?? []), true);
    }

    protected function isDocument(string $extension): bool
    {
        return in_array($extension, (array)($this->settings['file_types']['documents'] ?? []), true);
    }

    protected function isDownload(string $extension): bool
    {
        return in_array($extension, (array)($this->settings['file_types']['downloads'] ?? []), true);
    }

    protected function isZip(string $extension): bool
    {
        return $extension === 'zip';
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