<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 050
 * Version Info Foundation
 *
 * Ziel:
 * - VERSION und BUILD Dateien einführen
 * - zentrale Version-Klasse
 * - Dashboard und Settings können stabile Versionsdaten anzeigen
 * - Grundlage für spätere Update-API
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

    $writeIfMissing = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            $log("Datei existiert bereits, übersprungen: {$file}");
            return;
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 050 Version Info Foundation gestartet');

    $writeIfMissing($root . '/VERSION', "v0.5.0-alpha\n");
    $writeIfMissing($root . '/BUILD', "2026-06-11-001\n");

    $write($root . '/app/Core/System/Version.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core\System;

class Version
{
    public function __construct(
        protected string $root
    ) {
    }

    public function version(): string
    {
        return $this->readFile('VERSION', 'v0.0.0-dev');
    }

    public function build(): string
    {
        return $this->readFile('BUILD', 'local-dev');
    }

    public function channel(): string
    {
        $version = strtolower($this->version());

        if (str_contains($version, 'alpha')) {
            return 'alpha';
        }

        if (str_contains($version, 'beta')) {
            return 'beta';
        }

        if (str_contains($version, 'rc')) {
            return 'rc';
        }

        if (str_contains($version, 'dev')) {
            return 'dev';
        }

        return 'stable';
    }

    public function gitTag(): string
    {
        return $this->git('describe --tags --abbrev=0');
    }

    public function gitCommit(): string
    {
        return $this->git('rev-parse --short HEAD');
    }

    public function full(): string
    {
        return trim($this->version() . ' · Build ' . $this->build());
    }

    public function diagnostics(): array
    {
        return [
            'version' => $this->version(),
            'build' => $this->build(),
            'channel' => $this->channel(),
            'git_tag' => $this->gitTag(),
            'git_commit' => $this->gitCommit(),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
        ];
    }

    protected function readFile(string $name, string $fallback): string
    {
        $file = $this->root . '/' . $name;

        if (!file_exists($file)) {
            return $fallback;
        }

        $value = trim((string)file_get_contents($file));

        return $value !== '' ? $value : $fallback;
    }

    protected function git(string $command): string
    {
        if (!function_exists('shell_exec')) {
            return 'unavailable';
        }

        if (!is_dir($this->root . '/.git')) {
            return 'unavailable';
        }

        $result = @shell_exec('git -C ' . escapeshellarg($this->root) . ' ' . $command . ' 2>NUL');
        $result = trim((string)$result);

        return $result !== '' ? $result : 'unavailable';
    }
}
PHP);

    $settingsFile = $root . '/public/admin/settings/index.php';
    if (file_exists($settingsFile)) {
        $settings = file_get_contents($settingsFile);

        if (!str_contains($settings, 'use TreeForge\\Core\\System\\Version;')) {
            $settings = str_replace(
                'use TreeForge\\Core\\Settings\\SettingsManager;',
                "use TreeForge\\Core\\Settings\\SettingsManager;\nuse TreeForge\\Core\\System\\Version;",
                $settings
            );
        }

        if (!str_contains($settings, '$versionInfo = new Version($root);')) {
            $settings = str_replace(
                '$settings = new SettingsManager($root);',
                "$settings = new SettingsManager($root);\n\$versionInfo = new Version(\$root);",
                $settings
            );
        }

        $settings = preg_replace(
            "/\\\$version\\s*=\\s*'unknown';/",
            "\$version = \$versionInfo->version();",
            $settings
        );

        $settings = preg_replace(
            "/\\\$gitTag\\s*=\\s*trim\\(\\(string\\)@shell_exec\\([^;]+;\\s*\\\$gitCommit\\s*=\\s*trim\\(\\(string\\)@shell_exec\\([^;]+;/s",
            "\$build = \$versionInfo->build();\n\$channel = \$versionInfo->channel();\n\$gitTag = \$versionInfo->gitTag();\n\$gitCommit = \$versionInfo->gitCommit();",
            $settings
        );

        if (!str_contains($settings, '<dt>Build</dt>')) {
            $settings = str_replace(
                ". '<dt>TreeForge Version</dt><dd>' . e(\$version) . '</dd>'",
                ". '<dt>TreeForge Version</dt><dd>' . e(\$version) . '</dd>'\n"
                . "    . '<dt>Build</dt><dd>' . e(\$build ?? \$versionInfo->build()) . '</dd>'\n"
                . "    . '<dt>Update Channel</dt><dd>' . e(\$channel ?? \$versionInfo->channel()) . '</dd>'",
                $settings
            );
        }

        $write($settingsFile, $settings);
    }

    $dashboardFile = $root . '/public/admin/index.php';
    if (file_exists($dashboardFile)) {
        $dashboard = file_get_contents($dashboardFile);

        if (!str_contains($dashboard, 'use TreeForge\\Core\\System\\Version;')) {
            $dashboard = str_replace(
                'use TreeForge\\Core\\Settings\\SettingsManager;',
                "use TreeForge\\Core\\Settings\\SettingsManager;\nuse TreeForge\\Core\\System\\Version;",
                $dashboard
            );
        }

        if (!str_contains($dashboard, '$versionInfo = new Version($root);')) {
            $dashboard = str_replace(
                '$settings = new SettingsManager($root);',
                "$settings = new SettingsManager($root);\n\$versionInfo = new Version(\$root);",
                $dashboard
            );
        }

        $dashboard = preg_replace(
            "/\\\$gitTag\\s*=\\s*trim\\(\\(string\\)@shell_exec\\([^;]+;\\s*\\\$gitCommit\\s*=\\s*trim\\(\\(string\\)@shell_exec\\([^;]+;/s",
            "\$gitTag = \$versionInfo->gitTag();\n\$gitCommit = \$versionInfo->gitCommit();\n\$build = \$versionInfo->build();\n\$channel = \$versionInfo->channel();",
            $dashboard
        );

        $dashboard = str_replace(
            ". '<div class=\"tf-status-row\"><span><i class=\"tf-dot\"></i>Git</span><strong>' . e(\$gitTag ?: \$gitCommit ?: 'unknown') . '</strong></div>'",
            ". '<div class=\"tf-status-row\"><span><i class=\"tf-dot\"></i>Version</span><strong>' . e(\$versionInfo->version()) . '</strong></div>'\n"
            . "    . '<div class=\"tf-status-row\"><span><i class=\"tf-dot\"></i>Build</span><strong>' . e(\$build ?? \$versionInfo->build()) . '</strong></div>'\n"
            . "    . '<div class=\"tf-status-row\"><span><i class=\"tf-dot warning\"></i>Update Channel</span><strong>' . e(\$channel ?? \$versionInfo->channel()) . '</strong></div>'",
            $dashboard
        );

        $write($dashboardFile, $dashboard);
    }

    $write($root . '/docs/treeforge/40-version-info-foundation.md', <<<'MD'
# Version Info Foundation

Patch 050 ergänzt stabile Versionsinformationen.

## Dateien

```text
VERSION
BUILD
app/Core/System/Version.php
```

## Warum?

Git-Informationen sind im Backend nicht immer zuverlässig verfügbar.

Gründe:

- `.git` wird auf Live-Systemen oft nicht deployed
- `shell_exec()` kann deaktiviert sein
- Git ist unter Windows/Laragon nicht immer im PHP-Pfad
- `composer.json` enthält nicht zwingend eine Version

## Lösung

TreeForge liest primär:

```text
VERSION
BUILD
```

Git Tag und Commit sind nur zusätzliche Diagnosewerte.

## Später

Die Version-Klasse ist Grundlage für:

```text
Update API
Support-Diagnose
Systemstatus
Kompatibilitätsprüfung
Release Channel
```
MD);

    $log('Patch 050 Version Info Foundation fertig');
};
