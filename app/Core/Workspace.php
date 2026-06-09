<?php
declare(strict_types=1);

namespace TreeForge\Core;

use RuntimeException;

class Workspace
{
    public const PUBLISHED = 'published';
    public const DRAFT = 'draft';
    public const REVIEW = 'review';
    public const ARCHIVE = 'archive';

    protected string $root;
    protected string $name;
    protected ?string $lastEnsureMessage = null;

    public function __construct(string $root, string $name)
    {
        $allowed = [
            self::PUBLISHED,
            self::DRAFT,
            self::REVIEW,
            self::ARCHIVE,
        ];

        if (!in_array($name, $allowed, true)) {
            throw new RuntimeException("Invalid workspace: {$name}");
        }

        $this->root = rtrim($root, '/\\');
        $this->name = $name;
    }

    public static function published(string $root): self
    {
        return new self($root, self::PUBLISHED);
    }

    public static function draft(string $root): self
    {
        return new self($root, self::DRAFT);
    }

    public static function review(string $root): self
    {
        return new self($root, self::REVIEW);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->root . '/storage/workspaces/' . $this->name;
    }

    public function pagePath(string $pageId): string
    {
        return $this->path() . '/pages/' . $pageId . '.json';
    }

    public function hasPage(string $pageId): bool
    {
        return file_exists($this->pagePath($pageId));
    }

    public function lastEnsureMessage(): ?string
    {
        return $this->lastEnsureMessage;
    }

    public function ensurePage(string $pageId): void
    {
        $target = $this->pagePath($pageId);

        if (file_exists($target)) {
            $this->lastEnsureMessage = null;
            return;
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        $source = $this->findFallbackPage($pageId);

        if ($source !== null) {
            copy($source['file'], $target);
            $this->lastEnsureMessage = "Page '{$pageId}' wurde im Workspace '{$this->name}' aus '{$source['workspace']}' erzeugt.";
            return;
        }

        $emptyPage = [
            'id' => $pageId,
            'type' => 'page',
            'title' => ucfirst($pageId),
            'children' => [],
        ];

        file_put_contents(
            $target,
            json_encode($emptyPage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->lastEnsureMessage = "Leere Page '{$pageId}' wurde im Workspace '{$this->name}' erzeugt.";
    }

    protected function findFallbackPage(string $pageId): ?array
    {
        $preferred = match ($this->name) {
            self::REVIEW => [self::DRAFT, self::PUBLISHED],
            self::DRAFT => [self::PUBLISHED],
            self::PUBLISHED => [self::DRAFT],
            default => [self::DRAFT, self::PUBLISHED],
        };

        foreach ($preferred as $workspaceName) {
            $file = $this->root . '/storage/workspaces/' . $workspaceName . '/pages/' . $pageId . '.json';

            if (file_exists($file)) {
                return [
                    'workspace' => $workspaceName,
                    'file' => $file,
                ];
            }
        }

        return null;
    }

    public function loadPage(string $pageId): Page
    {
        $this->ensurePage($pageId);

        $file = $this->pagePath($pageId);

        if (!file_exists($file)) {
            throw new RuntimeException("Page not found in workspace {$this->name}: {$pageId}");
        }

        return new Page($file);
    }

    public function savePage(string $pageId, array $data): void
    {
        $file = $this->pagePath($pageId);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function publish(string $pageId): void
    {
        $draftFile = self::draft($this->root)->pagePath($pageId);
        $publishedFile = self::published($this->root)->pagePath($pageId);

        if (!file_exists($draftFile)) {
            throw new RuntimeException("Draft page not found: {$pageId}");
        }

        if (file_exists($publishedFile)) {
            $archiveDir = $this->root . '/storage/workspaces/archive/' . date('Y-m-d-His');

            if (!is_dir($archiveDir)) {
                mkdir($archiveDir, 0775, true);
            }

            copy($publishedFile, $archiveDir . '/' . $pageId . '.json');
        }

        if (!is_dir(dirname($publishedFile))) {
            mkdir(dirname($publishedFile), 0775, true);
        }

        copy($draftFile, $publishedFile);
    }
}