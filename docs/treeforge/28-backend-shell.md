# Backend Shell

Patch 038 ergänzt eine erste gemeinsame Backend-Shell.

## Dateien

```text
app/Admin/AdminMenu.php
app/Admin/AdminLayout.php
public/admin/index.php
public/assets/css/admin.css
public/admin/settings/index.php
```

## Routen

```text
/admin/
/admin/settings/
```

## Ziel

Admin-Seiten sollen nicht mehr jeweils eigene Header und Layouts mitbringen.

Stattdessen gibt es eine gemeinsame Shell mit:

- Sidebar
- Hauptnavigation
- Topbar
- Content-Bereich
- Quicklinks

## Vorbereitete Bereiche

```text
Dashboard
Explorer
Archive
Media
Templates
Nodes
Docs
Settings
```

Einige Bereiche sind noch als "geplant" markiert.