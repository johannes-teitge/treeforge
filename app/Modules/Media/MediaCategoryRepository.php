<?php
declare(strict_types=1);

namespace TreeForge\Modules\Media;

class MediaCategoryRepository
{
    protected string $file;

    public function __construct(protected string $root)
    {
        $this->file = $this->root . '/storage/media/categories.json';
    }

    public function all(): array
    {
        if (!file_exists($this->file)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($this->file), true);

        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $index => $category) {
            $data[$index] = $this->normalize($category);
        }

        usort($data, static fn(array $a, array $b): int => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

        return $data;
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $category) {
            if ((string)($category['id'] ?? '') === $id) {
                return $category;
            }
        }

        return null;
    }

    public function create(string $label, string $description = ''): array
    {
        $label = trim($label);

        if ($label === '') {
            throw new \RuntimeException('Kategoriename fehlt.');
        }

        $id = $this->slug($label);
        $categories = $this->all();

        $base = $id;
        $counter = 2;

        while ($this->containsId($categories, $id)) {
            $id = $base . '-' . $counter;
            $counter++;
        }

        $category = [
            'id' => $id,
            'label' => $label,
            'description' => trim($description),
            'icon' => 'folder',
            'color' => '',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        $categories[] = $category;
        $this->saveAll($categories);

        return $category;
    }

    public function update(string $id, array $values): array
    {
        $categories = $this->all();
        $found = false;
        $updated = null;

        foreach ($categories as $index => $category) {
            if ((string)($category['id'] ?? '') !== $id) {
                continue;
            }

            $label = trim((string)($values['label'] ?? $category['label'] ?? ''));

            if ($label === '') {
                throw new \RuntimeException('Kategoriename fehlt.');
            }

            $category['label'] = $label;
            $category['description'] = trim((string)($values['description'] ?? ''));
            $category['icon'] = trim((string)($values['icon'] ?? 'folder'));
            $category['color'] = trim((string)($values['color'] ?? ''));
            $category['updated_at'] = date('c');

            $categories[$index] = $this->normalize($category);
            $updated = $categories[$index];
            $found = true;
            break;
        }

        if (!$found || !$updated) {
            throw new \RuntimeException('Kategorie nicht gefunden.');
        }

        $this->saveAll($categories);

        return $updated;
    }

    public function delete(string $id, array $mediaItems): void
    {
        if ($id === '') {
            throw new \RuntimeException('Kategorie fehlt.');
        }

        foreach ($mediaItems as $item) {
            if ((string)($item['category'] ?? '') === $id) {
                throw new \RuntimeException('Kategorie kann nicht gelöscht werden, weil noch Medien zugeordnet sind.');
            }
        }

        $categories = array_values(array_filter(
            $this->all(),
            static fn(array $category): bool => (string)($category['id'] ?? '') !== $id
        ));

        $this->saveAll($categories);
    }

    public function labelFor(string $id): string
    {
        $category = $this->find($id);

        return $category ? (string)($category['label'] ?? $id) : $id;
    }

    public function counts(array $mediaItems): array
    {
        $counts = [
            '' => 0,
        ];

        foreach ($mediaItems as $item) {
            $category = (string)($item['category'] ?? '');
            $counts[$category] = ($counts[$category] ?? 0) + 1;

            if ($category === '') {
                $counts['']++;
            }
        }

        return $counts;
    }

    protected function saveAll(array $categories): void
    {
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0775, true);
        }

        file_put_contents(
            $this->file,
            json_encode(array_values($categories), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function containsId(array $categories, string $id): bool
    {
        foreach ($categories as $category) {
            if ((string)($category['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }

    protected function normalize(array $category): array
    {
        $category['id'] = (string)($category['id'] ?? $this->slug((string)($category['label'] ?? 'category')));
        $category['label'] = (string)($category['label'] ?? $category['id']);
        $category['description'] = (string)($category['description'] ?? '');
        $category['icon'] = (string)($category['icon'] ?? 'folder');
        $category['color'] = (string)($category['color'] ?? '');
        $category['created_at'] = (string)($category['created_at'] ?? date('c'));
        $category['updated_at'] = (string)($category['updated_at'] ?? $category['created_at']);

        return $category;
    }

    protected function slug(string $value): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'category';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'category';
    }
}