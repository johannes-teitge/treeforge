# Fix Delete Duplicate Persistence

Patch 102 behebt, dass Löschen und Duplizieren zwar `ok` melden, aber den JSON-Baum nicht verändern.

## Ursache

Die alten Methoden arbeiteten über:

```php
foreach ($node['children'] ?? [] as ...)
```

Bei verschachtelten Änderungen wurde dadurch nicht zuverlässig das Originalarray verändert.

## Fix

```php
removeNode()
insertAfterSibling()
```

arbeiten jetzt direkt auf:

```php
$node['children']
```

und verändern echte Array-Referenzen.