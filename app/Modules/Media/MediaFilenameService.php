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