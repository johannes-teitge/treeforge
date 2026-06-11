# Node-Hierarchie

Nodes können definieren, ob sie Children erlauben und welche Node-Typen erlaubt sind.

## Regeln

```php
public bool $hasChildren = true;

public array $allowedChildren = ['column'];

public array $allowedParents = ['columns'];
```

## Beispiel Columns

Columns darf nur Column-Nodes enthalten.

```php
class ColumnsNode extends AbstractTreeForgeNode
{
    public bool $hasChildren = true;

    public array $allowedChildren = [
        'column'
    ];
}
```

## Beispiel Column

Column darf viele Content-Nodes enthalten, aber nur unter Columns liegen.

```php
class ColumnNode extends AbstractTreeForgeNode
{
    public bool $hasChildren = true;

    public array $allowedChildren = ['*'];

    public array $allowedParents = [
        'columns'
    ];
}
```

## Beispiel Text

Text darf keine Children enthalten.

```php
class TextNode extends AbstractTreeForgeNode
{
    public bool $hasChildren = false;
}
```

## Prüfung im Core

```php
public function canAddChild(string $parentType, string $childType): bool
{
    $parent = $this->registry->get($parentType);
    $child  = $this->registry->get($childType);

    if (!$parent->hasChildren) {
        return false;
    }

    if (!in_array('*', $parent->allowedChildren, true)
        && !in_array($childType, $parent->allowedChildren, true)) {
        return false;
    }

    if (!empty($child->allowedParents)
        && !in_array($parentType, $child->allowedParents, true)) {
        return false;
    }

    return true;
}
```

Der Editor zeigt im Plus-Menü nur erlaubte Nodes an.
