# Auth & Users Concept

TreeForge benötigt ein eigenes Login- und Rechtesystem.

## Erste Stufe

```text
Admin Login
Logout
Session
geschütztes Backend
geschützte API-Routen
```

## Rollen

```text
Admin
Editor
Author
Viewer
```

## Rechte

Später granular:

```text
content.view
content.edit
content.publish
archive.view
archive.restore
settings.edit
media.upload
users.manage
updates.run
developer.nodes
```

## SQL User Storage

```sql
CREATE TABLE tf_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(120) NOT NULL UNIQUE,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(60) NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```
