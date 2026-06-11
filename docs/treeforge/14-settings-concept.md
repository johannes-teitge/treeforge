# Settings Concept

TreeForge benötigt zentrale Settings, damit Installer, Backend, Storage, Sprache, Security, Updates und Templates dieselben Werte verwenden.

## Bereiche

```text
Settings
├── General
├── Languages
├── Storage
├── Editor
├── Media
├── Security
├── Analytics
├── Updates
└── Developer
```

## General

```json
{
  "site_name": "TreeForge Website",
  "site_url": "https://example.com",
  "admin_email": "",
  "timezone": "Europe/Berlin"
}
```

## Languages

Keine leeren Sprachwerte verwenden. Auch wenn Multilanguage deaktiviert ist, wird intern immer eine Sprache gesetzt.

```json
{
  "default_language": "de",
  "enabled_languages": ["de"],
  "multilanguage": false
}
```

Die Default-Sprache kommt später aus dem Installer.

## Storage

```json
{
  "driver": "file",
  "database_path": "storage/database/treeforge.sqlite"
}
```

## Editor

```json
{
  "default_workspace": "draft",
  "autosave": false,
  "archive_limit": 50,
  "allow_html_node": true,
  "allow_raw_script": false
}
```

## Media

```json
{
  "upload_path": "storage/media",
  "public_path": "/media",
  "max_file_size_mb": 10,
  "allowed_types": ["jpg", "jpeg", "png", "webp", "svg", "pdf"]
}
```

## Security

```json
{
  "waf_enabled": true,
  "rate_limit_enabled": true,
  "max_requests_per_minute": 120,
  "block_wordpress_probes": true,
  "block_sqli_patterns": true,
  "block_xss_patterns": true,
  "login_protection": true,
  "retention_days": 30
}
```

## Analytics

```json
{
  "enabled": true,
  "anonymize_ip": true,
  "respect_do_not_track": true,
  "retention_days": 30,
  "count_bots": true
}
```

## Updates

```json
{
  "channel": "alpha",
  "update_server": "https://treeforge.de/api/updates",
  "auto_update": false,
  "verify_signature": true
}
```

## Developer

```json
{
  "debug": true,
  "custom_nodes_enabled": true,
  "cache_enabled": false,
  "maintenance_mode": false
}
```
