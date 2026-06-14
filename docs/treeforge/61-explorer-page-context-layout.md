# Explorer Page Context + Layout Refresh Foundation

Patch 071 macht den Explorer seitenbewusst und schafft mehr Platz.

## Neu

```text
/admin/explorer/?page=home
/admin/explorer/?page=kontakt
/admin/explorer/?page=ueber-uns
```

## Layout

Vorher:

```text
Workspaces | Tree | Inspector
```

Jetzt:

```text
Workspaces / Archive oben
Tree | Inspector
```

## Hinweis

Der Patch versucht `ExplorerController.php` automatisch auf `$pageId` umzubauen.
Wenn die Controller-Struktur stark abweicht, muss der Controller separat angepasst werden.