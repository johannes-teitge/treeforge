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