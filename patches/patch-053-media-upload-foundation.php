<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 053
 * Media Upload Foundation
 *
 * Ziel:
 * - Upload-Zone in /admin/media/
 * - Upload-Endpoint /api/media/upload.php
 * - Media Settings prüfen: Größe, Dateitypen, SVG, ZIP
 * - Dateinamen normalisieren und eindeutig machen
 * - Meta-Datei automatisch erzeugen
 * - erste AJAX-Upload-Logik
 *
 * Noch nicht enthalten:
 * - echter SVG Sanitizer
 * - Chunk Upload
 * - Replace / Versioning
 * - Kategorien-Drag&Drop
 * - Media Picker
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

    $log('Patch 053 Media Upload Foundation gestartet');

    $write($root . '/app/Modules/Media/MediaFilenameService.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

class MediaFilenameService
{
    public function normalize(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_-]+/', '-', $name) ?: 'media';
        $name = trim($name, '-_');

        if ($name === '') {
            $name = 'media';
        }

        return $extension !== '' ? $name . '.' . $extension : $name;
    }

    public function unique(string $directory, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $candidate = $filename;
        $counter = 2;

        while (file_exists($directory . '/' . $candidate)) {
            $candidate = $name . '-' . $counter . ($extension !== '' ? '.' . $extension : '');
            $counter++;
        }

        return $candidate;
    }
}
PHP);

    $write($root . '/app/Modules/Media/MediaUploadService.php', <<<'PHP'
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
PHP);

    $write($root . '/public/api/media/upload.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Modules\Media\MediaUploadService;

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 3);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Nur POST erlaubt.');
    }

    if (empty($_FILES['files'])) {
        throw new RuntimeException('Keine Dateien empfangen.');
    }

    $service = new MediaUploadService($root);
    $result = [];

    $files = $_FILES['files'];

    if (is_array($files['name'])) {
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $result[] = $service->upload([
                'name' => $files['name'][$i],
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ]);
        }
    } else {
        $result[] = $service->upload($files);
    }

    echo json_encode([
        'ok' => true,
        'files' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
PHP);

    $mediaIndex = $root . '/public/admin/media/index.php';
    if (file_exists($mediaIndex)) {
        $content = file_get_contents($mediaIndex);

        if (!str_contains($content, 'tf-media-upload-zone')) {
            $content = str_replace(
                ". '<section class=\"tf-media-grid-shell\">'",
                ". '<section class=\"tf-media-upload-zone\" id=\"tf-media-upload-zone\">'\n"
                . "    . '<div class=\"tf-media-upload-icon\">↑</div>'\n"
                . "    . '<div><h3>Bilder und Dateien hochladen</h3><p>Dateien hierher ziehen oder über den Button auswählen. Regeln kommen aus den Media Settings.</p><div id=\"tf-media-upload-result\" class=\"tf-media-upload-result\"></div></div>'\n"
                . "    . '<div><input type=\"file\" id=\"tf-media-upload-input\" multiple hidden><button type=\"button\" class=\"tf-admin-button\" id=\"tf-media-upload-select\">Dateien auswählen</button></div>'\n"
                . "    . '</section>'\n"
                . "    . '<section class=\"tf-media-grid-shell\">'",
                $content
            );
        }

        if (!str_contains($content, '/assets/js/media-upload.js')) {
            $content = str_replace(
                ');',
                ");\n\necho '<script src=\"/assets/js/media-upload.js\"></script>';",
                $content
            );
        }

        $write($mediaIndex, $content);
    }

    $write($root . '/public/assets/js/media-upload.js', <<<'JS'
(function () {
  const zone = document.getElementById('tf-media-upload-zone');
  const input = document.getElementById('tf-media-upload-input');
  const select = document.getElementById('tf-media-upload-select');
  const result = document.getElementById('tf-media-upload-result');

  if (!zone || !input || !select || !result) {
    return;
  }

  function message(text, ok) {
    result.textContent = text;
    result.className = 'tf-media-upload-result ' + (ok ? 'success' : 'error');
  }

  function upload(files) {
    if (!files || !files.length) {
      return;
    }

    const data = new FormData();

    Array.from(files).forEach(file => {
      data.append('files[]', file);
    });

    message('Upload läuft...', true);

    fetch('/api/media/upload.php', {
      method: 'POST',
      body: data
    })
      .then(response => response.json())
      .then(json => {
        if (!json.ok) {
          throw new Error(json.error || 'Upload fehlgeschlagen.');
        }

        message(json.files.length + ' Datei(en) hochgeladen. Seite wird neu geladen...', true);

        setTimeout(() => {
          window.location.reload();
        }, 700);
      })
      .catch(error => {
        message(error.message, false);
      });
  }

  select.addEventListener('click', () => input.click());

  input.addEventListener('change', () => {
    upload(input.files);
  });

  zone.addEventListener('dragover', event => {
    event.preventDefault();
    zone.classList.add('dragover');
  });

  zone.addEventListener('dragleave', () => {
    zone.classList.remove('dragover');
  });

  zone.addEventListener('drop', event => {
    event.preventDefault();
    zone.classList.remove('dragover');
    upload(event.dataTransfer.files);
  });
})();
JS);

    $css = $root . '/public/assets/css/media.css';
    if (file_exists($css)) {
        $mediaCss = file_get_contents($css);

        if (!str_contains($mediaCss, '.tf-media-upload-zone')) {
            $mediaCss .= <<<'CSS'

.tf-media-upload-zone {
  display: grid;
  grid-template-columns: 58px minmax(0, 1fr) auto;
  gap: 1rem;
  align-items: center;
  background: var(--tf-bg-card, #FFFFFF);
  border: 2px dashed var(--tf-border-strong, #B7C1C8);
  border-radius: var(--tf-radius-md, .75rem);
  padding: 1rem;
  margin-bottom: 1rem;
}

.tf-media-upload-zone.dragover {
  background: var(--tf-bg-hover, #EAF1F5);
  border-color: var(--tf-color-secondary, #E2A900);
}

.tf-media-upload-icon {
  width: 54px;
  height: 54px;
  border-radius: var(--tf-radius-md, .75rem);
  background: linear-gradient(145deg, #FFFFFF, #D9E0E5);
  border: 1px solid var(--tf-border-default, #D7DDE2);
  color: var(--tf-text-muted, #64727D);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.45rem;
  font-weight: 650;
}

.tf-media-upload-zone h3 {
  margin: 0 0 .2rem;
  color: var(--tf-text-heading, #071725);
  font-size: 1rem;
  font-weight: 620;
}

.tf-media-upload-zone p {
  margin: 0;
  color: var(--tf-text-muted, #64727D);
}

.tf-media-upload-result {
  margin-top: .4rem;
  font-weight: 500;
  font-size: .9rem;
}

.tf-media-upload-result.success {
  color: var(--tf-state-success-text, #15713A);
}

.tf-media-upload-result.error {
  color: var(--tf-state-danger-text, #C62828);
}

@media (max-width: 760px) {
  .tf-media-upload-zone {
    grid-template-columns: 1fr;
  }
}
CSS;

            $write($css, $mediaCss);
        }
    }

    $write($root . '/docs/treeforge/43-media-upload-foundation.md', <<<'MD'
# Media Upload Foundation

Patch 053 ergänzt den ersten echten Upload im Media Manager.

## Route

```text
/admin/media/
```

## Endpoint

```text
/api/media/upload.php
```

## Features

- Drag & Drop Upload
- Mehrfachupload
- Upload per AJAX
- Dateigröße aus Media Settings
- Dateitypen aus Media Settings
- SVG-Regeln
- ZIP/Download-Regeln
- Dateinamen normalisieren
- eindeutige Namen erzeugen
- Meta-Datei automatisch erzeugen

## Speicherung

Bilder:

```text
storage/media/originals/YYYY/MM/
```

Dokumente:

```text
storage/media/originals/documents/YYYY/MM/
```

Downloads:

```text
storage/media/originals/downloads/YYYY/MM/
```

Meta:

```text
storage/media/meta/...datei.ext.json
```

## Noch offen

- echter SVG Sanitizer
- Replace / Versioning
- Media Edit
- Kategorien
- Media Picker
- Render Cache
MD);

    $log('Patch 053 Media Upload Foundation fertig');
};
