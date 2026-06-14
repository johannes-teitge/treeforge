<?php
declare(strict_types=1);

namespace TreeForge\Core\Templates;

/**
 * Veröffentlicht Template-Assets aus nicht-öffentlichen Quellordnern nach public/.
 *
 * Grundregel:
 * - core/templates/assets/... ist Quelle und nicht direkt öffentlich.
 * - public/assets/treeforge/... ist Browser-Ziel.
 * - Kopiert wird nur, wenn Ziel fehlt oder sich der Dateiinhalt geändert hat.
 */
class TemplateAssetPublisher
{
    public function __construct(
        protected string $root
    ) {
        $this->root = rtrim($this->root, '/\\');
    }

    /**
     * @return array<int,string> Öffentliche CSS-URLs inklusive Cache-Buster.
     */
    public function publishCoreCss(): array
    {
        $urls = [];

        $url = $this->publishFile(
            $this->root . '/core/templates/assets/css/core-template.css',
            $this->root . '/public/assets/treeforge/core/css/core-template.css',
            '/assets/treeforge/core/css/core-template.css'
        );

        if ($url !== null) {
            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * Spätere Erweiterung für User-Templates.
     *
     * Quelle:
     *   templates/<templateId>/assets/css/*.css
     * Ziel:
     *   public/assets/treeforge/templates/<templateId>/css/*.css
     *
     * @return array<int,string>
     */
    public function publishTemplateCss(string $templateId): array
    {
        $templateId = $this->cleanTemplateId($templateId);
        if ($templateId === '') {
            return [];
        }

        $sourceDir = $this->root . '/templates/' . $templateId . '/assets/css';
        if (!is_dir($sourceDir)) {
            return [];
        }

        $files = glob($sourceDir . '/*.css') ?: [];
        sort($files);

        $urls = [];
        foreach ($files as $source) {
            $filename = basename($source);
            $url = $this->publishFile(
                $source,
                $this->root . '/public/assets/treeforge/templates/' . $templateId . '/css/' . $filename,
                '/assets/treeforge/templates/' . $templateId . '/css/' . $filename
            );

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    public function publishFile(string $source, string $target, string $publicUrl): ?string
    {
        if (!file_exists($source) || !is_file($source)) {
            return null;
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        $sourceHash = sha1_file($source) ?: '';
        $targetHash = file_exists($target) ? (sha1_file($target) ?: '') : '';

        if (!file_exists($target) || $sourceHash === '' || $sourceHash !== $targetHash) {
            copy($source, $target);
        }

        $version = file_exists($target) ? substr((string)sha1_file($target), 0, 12) : substr($sourceHash, 0, 12);
        if ($version === '') {
            $version = (string)time();
        }

        return $publicUrl . '?v=' . rawurlencode($version);
    }

    protected function cleanTemplateId(string $templateId): string
    {
        $templateId = strtolower(trim($templateId));
        return preg_replace('/[^a-z0-9_-]/', '', $templateId) ?: '';
    }
}