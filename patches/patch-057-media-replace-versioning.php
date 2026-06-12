<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 057
 * Media Replace / Versioning
 *
 * Ziel:
 * - Datei im Media-Editor ersetzen
 * - Media-ID bleibt erhalten
 * - Metadaten bleiben erhalten
 * - Ziel-Dateiname bleibt stabil
 * - alte Datei wird unter storage/media/versions/... gesichert
 * - Versionseintrag in Meta JSON
 *
 * Hinweis:
 * Formatwechsel ist bewusst noch nicht erlaubt.
 * JPG ersetzt JPG, PNG ersetzt PNG, SVG ersetzt SVG usw.
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

    $log('Patch 057 Media Replace / Versioning gestartet');

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

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $originalUploadName = (string)($file['name'] ?? '');
        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($originalUploadName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Ungültiger Upload.');
        }

        $currentExtension = strtolower((string)($item['extension'] ?? pathinfo((string)($item['filename'] ?? ''), PATHINFO_EXTENSION)));
        $newExtension = strtolower(pathinfo($originalUploadName, PATHINFO_EXTENSION));

        if ($currentExtension === '' || $newExtension === '') {
            throw new RuntimeException('Dateierweiterung fehlt.');
        }

        if ($currentExtension !== $newExtension) {
            throw new RuntimeException('Formatwechsel ist aktuell nicht erlaubt. Erwartet: .' . $currentExtension . ', erhalten: .' . $newExtension);
        }

        $this->assertSize($size, $currentExtension);
        $this->assertSafeName($originalUploadName, $newExtension);

        $relativePath = (string)($item['relative_path'] ?? '');

        if ($relativePath === '') {
            throw new RuntimeException('relative_path fehlt.');
        }

        $target = rtrim($this->config->originalsDir(), '/\\') . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

        if (!file_exists($target)) {
            throw new RuntimeException('Originaldatei nicht gefunden: ' . $relativePath);
        }

        $versionsDir = $this->versionsDir($relativePath);

        if (!is_dir($versionsDir)) {
            mkdir($versionsDir, 0775, true);
        }

        $versionName = date('Ymd-His') . '-' . basename($relativePath);
        $versionTarget = $versionsDir . '/' . $versionName;

        if (!copy($target, $versionTarget)) {
            throw new RuntimeException('Alte Version konnte nicht gesichert werden.');
        }

        if (!move_uploaded_file($tmpName, $target)) {
            throw new RuntimeException('Neue Datei konnte nicht gespeichert werden.');
        }

        clearstatcache(true, $target);

        $imageSize = @getimagesize($target);
        $mime = function_exists('mime_content_type') ? (string)mime_content_type($target) : (string)($item['mime'] ?? '');

        $versions = (array)($item['versions'] ?? []);
        $versions[] = [
            'file' => $this->versionRelativePath($versionTarget),
            'original_name' => (string)($item['original_name'] ?? $item['filename'] ?? ''),
            'replaced_by' => $originalUploadName,
            'size' => (int)($item['size'] ?? 0),
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'mime' => (string)($item['mime'] ?? ''),
            'created_at' => date('c'),
        ];

        $maxVersions = max(1, (int)($this->settings['replace']['max_versions'] ?? 10));

        if (count($versions) > $maxVersions) {
            $remove = array_splice($versions, 0, count($versions) - $maxVersions);

            foreach ($remove as $oldVersion) {
                $oldFile = $this->root . '/' . ltrim((string)($oldVersion['file'] ?? ''), '/\\');
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }

        $item['versions'] = $versions;
        $item['original_name'] = $originalUploadName;
        $item['mime'] = $mime;
        $item['size'] = filesize($target) ?: 0;
        $item['width'] = is_array($imageSize) ? ($imageSize[0] ?? null) : null;
        $item['height'] = is_array($imageSize) ? ($imageSize[1] ?? null) : null;
        $item['replaced_at'] = date('c');

        $this->repo->save($item);

        return $this->repo->findById($id) ?? $item;
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

    protected function assertSafeName(string $filename, string $extension): void
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

    protected function versionsDir(string $relativePath): string
    {
        return $this->root . '/storage/media/versions/' . trim(dirname(str_replace('\\', '/', $relativePath)), '/.');
    }

    protected function versionRelativePath(string $absolutePath): string
    {
        return ltrim(str_replace('\\', '/', substr($absolutePath, strlen($this->root))), '/');
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

    $editFile = $root . '/public/admin/media/edit.php';

    if (file_exists($editFile)) {
        $edit = file_get_contents($editFile);

        if (!str_contains($edit, 'use TreeForge\\Modules\\Media\\MediaReplaceService;')) {
            $edit = str_replace(
                'use TreeForge\\Modules\\Media\\MediaRepository;',
                "use TreeForge\\Modules\\Media\\MediaRepository;\nuse TreeForge\\Modules\\Media\\MediaReplaceService;",
                $edit
            );
        }

        if (!str_contains($edit, '$replaceService = new MediaReplaceService($root);')) {
            $edit = str_replace(
                '$repo = new MediaRepository($root);',
                "$repo = new MediaRepository($root);\n\$replaceService = new MediaReplaceService(\$root);",
                $edit
            );
        }

        $old = <<<'PHP'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $item['title'] = trim((string)($_POST['title'] ?? ''));
PHP;

        $new = <<<'PHP'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['replace_file']) && !empty($_FILES['replacement'])) {
            $item = $replaceService->replace($id, $_FILES['replacement']);
            $saved = true;
        }

        $item['title'] = trim((string)($_POST['title'] ?? $item['title'] ?? ''));
PHP;

        if (str_contains($edit, $old)) {
            $edit = str_replace($old, $new, $edit);
        }

        if (!str_contains($edit, 'Datei ersetzen / Versionierung')) {
            $insert = <<<'PHP'
    . '<h3>Datei ersetzen / Versionierung</h3>'
    . '<p>Die Media-ID und der Ziel-Dateiname bleiben erhalten. Die bisherige Datei wird als Version gesichert. Formatwechsel ist aktuell nicht erlaubt.</p>'
    . '<label><span>Neue Datei</span><input type="file" name="replacement"><small>Erlaubt ist aktuell nur derselbe Dateityp wie die Originaldatei.</small></label>'
    . '<button type="submit" name="replace_file" value="1" class="tf-admin-button secondary">Datei ersetzen</button>'

PHP;

            $edit = str_replace(
                "    . '<h3>Rechte / Quelle</h3>'",
                $insert . "    . '<h3>Rechte / Quelle</h3>'",
                $edit
            );
        }

        if (!str_contains($edit, 'Versionen</h3>')) {
            $versionBlock = <<<'PHP'

$versions = (array)($item['versions'] ?? []);

if ($versions !== []) {
    $content .= '<div class="tf-media-versions tf-admin-card">'
        . '<h3>Versionen</h3>'
        . '<table class="tf-dashboard-table">'
        . '<thead><tr><th>Zeitpunkt</th><th>Datei</th><th>Ersetzt durch</th><th>Größe</th></tr></thead>'
        . '<tbody>';

    foreach (array_reverse($versions) as $version) {
        $content .= '<tr>'
            . '<td>' . e($version['created_at'] ?? '') . '</td>'
            . '<td><code>' . e($version['file'] ?? '') . '</code></td>'
            . '<td>' . e($version['replaced_by'] ?? '') . '</td>'
            . '<td>' . e(bytesHuman((int)($version['size'] ?? 0))) . '</td>'
            . '</tr>';
    }

    $content .= '</tbody></table></div>';
}

PHP;

            $edit = str_replace(
                "echo (new AdminLayout())->render(",
                $versionBlock . "\necho (new AdminLayout())->render(",
                $edit
            );
        }

        // Ensure form supports file upload
        $edit = str_replace(
            '<form method="post" class="tf-media-edit-layout">',
            '<form method="post" enctype="multipart/form-data" class="tf-media-edit-layout">',
            $edit
        );

        $write($editFile, $edit);
    }

    $cssFile = $root . '/public/assets/css/media.css';
    $css = file_exists($cssFile) ? file_get_contents($cssFile) : '';

    if (!str_contains($css, 'PATCH 057 MEDIA REPLACE')) {
        $css .= <<<'CSS'

/* PATCH 057 MEDIA REPLACE */

.tf-media-versions {
  margin-top: 1rem;
}

.tf-media-versions h3 {
  margin-top: 0;
}

.tf-media-edit-form input[type="file"] {
  display: block;
  width: 100%;
  border: 1px dashed var(--tf-border-strong, #B7C1C8);
  border-radius: var(--tf-radius-sm, .5rem);
  padding: .65rem;
  background: var(--tf-bg-hover, #EAF1F5);
}
CSS;

        $write($cssFile, $css);
    }

    $write($root . '/docs/treeforge/47-media-replace-versioning.md', <<<'MD'
# Media Replace / Versioning

Patch 057 ergänzt Datei-Ersetzen mit Versionierung.

## Verhalten

```text
Media-ID bleibt gleich
relative_path bleibt gleich
öffentlicher Ziel-Dateiname bleibt gleich
Metadaten bleiben erhalten
alte Datei wird als Version gesichert
```

## Speicherort alter Versionen

```text
storage/media/versions/...
```

## Formatwechsel

Aktuell bewusst nicht erlaubt.

```text
jpg ersetzt jpg
png ersetzt png
svg ersetzt svg
zip ersetzt zip
```

Warum?

Bestehende URLs und Renderer bleiben stabil.

## Später möglich

- Formatwechsel optional erlauben
- Cache invalidieren
- Version zurückholen
- Versionsvergleich
MD);

    $log('Patch 057 Media Replace / Versioning fertig');
};
