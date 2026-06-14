# Fix Nested Add Persistence

Patch 098 behebt, dass eingefügte Nodes nicht im Baum erscheinen.

## Ursache

Die alte `findNodeRef()`-Logik verlor bei verschachtelten Nodes die echte Referenz.

## Fix

Einfügen läuft nun über:

```php
appendChildToNode()
```

Diese Methode hängt die Node rekursiv direkt in den JSON-Baum ein.

## Betroffen

- add
- paste-copy
- paste-reference

## Beispiel

```text
Column → Button hinzufügen
```

wird nun korrekt gespeichert.