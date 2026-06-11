<?php
declare(strict_types=1);

namespace TreeForge\Modules\Archives;

use TreeForge\Core\ArchiveManager;

class ArchivesController
{
    public function __construct(
        protected string $root
    ) {
    }

    public function handle(): string
    {
        $pageId = (string)($_GET['page'] ?? 'home');
        $query = trim((string)($_GET['q'] ?? ''));
        $dateFrom = trim((string)($_GET['date_from'] ?? ''));
        $dateTo = trim((string)($_GET['date_to'] ?? ''));

        $archive = new ArchiveManager($this->root);
        $versions = $archive->getVersions($pageId);

        $versions = $this->filterVersions($versions, $pageId, $query, $dateFrom, $dateTo);

        return (new ArchivesRenderer())->render([
            'page' => $pageId,
            'q' => $query,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'versions' => $versions,
            'total' => count($versions),
        ]);
    }

    protected function filterVersions(array $versions, string $pageId, string $query, string $dateFrom, string $dateTo): array
    {
        $fromTimestamp = $this->dateInputToTimestamp($dateFrom, false);
        $toTimestamp = $this->dateInputToTimestamp($dateTo, true);
        $queryLower = mb_strtolower($query);

        return array_values(array_filter($versions, function (array $version) use ($pageId, $queryLower, $fromTimestamp, $toTimestamp): bool {
            $id = (string)($version['version'] ?? '');
            $createdAt = (string)($version['created_at'] ?? '');
            $format = (string)($version['format'] ?? '');

            if ($queryLower !== '') {
                $haystack = mb_strtolower($id . ' ' . $createdAt . ' ' . $format . ' ' . $pageId);

                if (!str_contains($haystack, $queryLower)) {
                    return false;
                }
            }

            $versionTimestamp = $this->versionToTimestamp($id);

            if ($fromTimestamp !== null && $versionTimestamp !== null && $versionTimestamp < $fromTimestamp) {
                return false;
            }

            if ($toTimestamp !== null && $versionTimestamp !== null && $versionTimestamp > $toTimestamp) {
                return false;
            }

            return true;
        }));
    }

    protected function dateInputToTimestamp(string $date, bool $endOfDay): ?int
    {
        if ($date === '') {
            return null;
        }

        $time = strtotime($date . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));

        return $time === false ? null : $time;
    }

    protected function versionToTimestamp(string $version): ?int
    {
        $date = \DateTime::createFromFormat('Y-m-d-His', $version);

        if (!$date instanceof \DateTime) {
            return null;
        }

        return $date->getTimestamp();
    }
}