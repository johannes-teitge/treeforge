# Storage Concept

TreeForge startet mit FileStorage und JSON-Dateien.

Langfristig wird Storage über ein Interface abstrahiert.

## Ziel

Die Anwendung soll nicht wissen, ob Daten aus Dateien, SQLite oder MySQL kommen.

```php
$storage->loadTree('home', 'published', 'de');
$storage->saveTree('home', 'draft', 'de', $tree);
$storage->listArchives('home', 'de');
```

## Adapter

```text
StorageInterface
├── FileStorageAdapter
├── SQLiteStorageAdapter
└── MySQLStorageAdapter
```

## SQL nicht übernormalisieren

Node-Daten bleiben flexibel. Deshalb werden Trees zuerst als JSON gespeichert.

```sql
CREATE TABLE tf_content_trees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content_id VARCHAR(120) NOT NULL,
    workspace VARCHAR(40) NOT NULL,
    lang VARCHAR(10) NOT NULL,
    tree_json TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE(content_id, workspace, lang)
);
```

## Archive

```sql
CREATE TABLE tf_archives (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content_id VARCHAR(120) NOT NULL,
    lang VARCHAR(10) NOT NULL,
    version VARCHAR(40) NOT NULL,
    tree_json TEXT NOT NULL,
    created_at DATETIME NOT NULL
);
```

## Migration

```text
FileStorage
↓
Export kompletter Trees
↓
SQLStorageAdapter
↓
Import in tf_content_trees
↓
Routen neu generieren
```
