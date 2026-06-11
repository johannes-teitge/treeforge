# Node-System

Jede Node erweitert eine gemeinsame Basis-Node.

## Pflichtangaben

- type
- label
- icon
- category
- hasChildren
- allowedChildren
- allowedParents

## Basis-Klasse

```php
abstract class AbstractTreeForgeNode
{
    public string $type;
    public string $label;
    public string $icon = 'fa-solid fa-cube';
    public string $category = 'Content';

    public bool $hasChildren = false;

    public array $allowedChildren = [];
    public array $allowedParents = [];

    abstract public function getDefaultData(): array;

    abstract public function getEditorSchema(): array;

    abstract public function render(array $data, array $children = []): string;
}
```

## Prinzip

Der Editor soll keine Sonderlogik pro Node-Typ enthalten.

Nicht so:

```php
if ($type === 'image') {
    // Speziallogik
}
```

Sondern so:

```php
$node = $registry->get($type);
$schema = $node->getEditorSchema();
```

Dadurch können neue Nodes später als Erweiterung eingebunden werden.
