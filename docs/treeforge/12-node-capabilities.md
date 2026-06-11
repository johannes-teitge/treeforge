# Node-Capabilities

Capabilities beschreiben, welche Aktionen für eine Node erlaubt sind.

Damit müssen Sonderfälle nicht hart im Editor-Code verdrahtet werden.

## Standard-Capabilities

```php
public function getCapabilities(): array
{
    return [
        'renderable' => true,
        'editable'  => true,
        'sortable'  => true,
        'cloneable' => true,
        'deletable' => true,
        'movable'   => true
    ];
}
```

## Beispiele

### RootNode

Eine RootNode darf nicht gelöscht oder verschoben werden.

```php
public function getCapabilities(): array
{
    return [
        'renderable' => true,
        'editable'  => true,
        'sortable'  => false,
        'cloneable' => false,
        'deletable' => false,
        'movable'   => false
    ];
}
```

### ColumnNode

Eine ColumnNode kann später eventuell nicht frei verschoben werden, sondern nur innerhalb einer ColumnsNode.

```php
public function getCapabilities(): array
{
    return [
        'renderable' => true,
        'editable'  => true,
        'sortable'  => true,
        'cloneable' => false,
        'deletable' => true,
        'movable'   => true
    ];
}
```

### SystemNode

SystemNodes können sichtbar, aber nicht direkt bearbeitbar sein.

```php
public function getCapabilities(): array
{
    return [
        'renderable' => true,
        'editable'  => false,
        'sortable'  => false,
        'cloneable' => false,
        'deletable' => false,
        'movable'   => false
    ];
}
```

## Verwendung im Editor

Der Editor fragt die Capabilities ab und zeigt nur erlaubte Aktionen.

```php
$capabilities = $nodeDefinition->getCapabilities();

if ($capabilities['deletable']) {
    // Delete-Button anzeigen
}
```

## Ziel

Capabilities verhindern viele Sonderfälle im Editor.

Der Editor muss nicht wissen, was eine RootNode, SystemNode oder ColumnNode ist.

Er folgt nur den Regeln der jeweiligen Node.
