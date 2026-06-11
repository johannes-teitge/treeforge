<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 035
 * TreeForge Master Concept
 *
 * Ziel:
 * - zentrale Konzeptdokumentation für die nächsten Architektur-Schritte
 * - Backend, Settings, Storage, Routing, RootNode, Security, Analytics,
 *   Templates, Updates und Installer festhalten
 * - keine produktiven Codeänderungen
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 035 TreeForge Master Concept gestartet');

    $write($root . '/docs/treeforge/13-master-concept.md', <<<'MD'
# TreeForge Master Concept

TreeForge ist ein node-basiertes CMS und Site-Builder-System.

Ziel ist nicht, einen klassischen Pagebuilder nachzubauen, sondern Inhalte als klar strukturierte Content-Trees zu verwalten.

## Hauptbereiche

```text
TreeForge
├── Core
├── Backend
├── Settings
├── Storage
├── Routing
├── RootNode / Content Types
├── Security
├── Analytics
├── Auth / Users
├── Templates / Themes / Site Packages
├── Updates
└── Installer
```

## Grundprinzip

Das Node-System bleibt für alle Inhalte gleich.

Die RootNode definiert:

- Content-Typ
- SEO-Daten
- Social-Daten
- Routing
- Slug
- Status
- Sprache
- Flags
- Overview-Daten

## Zielarchitektur

```text
Request
↓
Bootstrap
↓
Mini-WAF
↓
Router
↓
Controller
↓
Storage
↓
Renderer
↓
Response
```

## Leitlinien

- JSON First
- Storage austauschbar
- SQL speichert anfangs komplette Trees als JSON
- RootNode ist die Seiteneinstellung
- Custom Nodes über Manifest
- Templates und Site Packages sind exportierbar
- Backend wird modular aufgebaut
- Security/WAF wird früh im Bootstrap geprüft
- Updates laufen später über treeforge.de

MD);

    $write($root . '/docs/treeforge/14-settings-concept.md', <<<'MD'
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

MD);

    $write($root . '/docs/treeforge/15-storage-concept.md', <<<'MD'
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

MD);

    $write($root . '/docs/treeforge/16-security-analytics-concept.md', <<<'MD'
# Security & Analytics Concept

TreeForge soll früh eine kleine Security-Schicht bekommen.

## Request Pipeline

```text
Request
↓
Bootstrap
↓
Mini-WAF
↓
Rate Limit
↓
Security Log
↓
Router
```

## Mini-WAF

Der Mini-WAF soll einfache Angriffe und Bot-Probes blockieren.

Beispiele:

```text
/wp-admin
/wp-login.php
/xmlrpc.php
/.env
/composer.json
/vendor/
/../
php://
base64_decode
eval(
UNION SELECT
<script
```

## Lokale Analytics

TreeForge soll eine kleine lokale Statistik besitzen.

Keine externen Tracker.

```text
tf_visits
- id
- created_at
- ip_hash
- user_agent_hash
- path
- referrer
- method
- status_code
- bot_score
```

## Datenschutz

- IP nicht im Klartext speichern
- IP hashen/anonymisieren
- Do-Not-Track respektieren
- Retention-Tage einstellbar
- Analytics abschaltbar

MD);

    $write($root . '/docs/treeforge/17-auth-users-concept.md', <<<'MD'
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

MD);

    $write($root . '/docs/treeforge/18-template-system-concept.md', <<<'MD'
# Template System Concept

TreeForge soll Templates, Themes, Demo Pages und Site Packages unterstützen.

## Begriffe

```text
Theme
= globales Design, CSS, Header/Footer, Design Tokens

Template
= wiederverwendbarer Node-Baum

Demo Page
= vollständige Beispielseite mit Medien und Inhalt

Preset
= gespeicherte Einstellungen einer Node

Global Block
= wiederverwendbarer Node-Bereich
```

## Struktur

```text
themes/
└── clean-business/
    ├── theme.json
    ├── assets/
    ├── templates/
    │   ├── landingpage.json
    │   ├── about.json
    │   └── contact.json
    └── demos/
        └── agency-demo.zip
```

## Site Package

```text
business-starter.zip
├── manifest.json
├── pages/
├── media/
├── themes/
├── nodes/
└── config/
```

## Import-Prüfung

- TreeForge-Version kompatibel?
- benötigte Nodes vorhanden?
- benötigte Module vorhanden?
- Medien vollständig?
- keine gefährlichen Dateien?
- Konflikte mit vorhandenen Slugs?
- sichere Dateitypen?

MD);

    $write($root . '/docs/treeforge/19-update-system-concept.md', <<<'MD'
# Update System Concept

TreeForge soll später Updates über treeforge.de ausliefern können.

## Update API

```text
treeforge.de/api/updates
```

liefert:

```json
{
  "latest": "0.4.0-alpha",
  "channel": "alpha",
  "download_url": "",
  "checksum": "",
  "signature": "",
  "changelog": []
}
```

## Ablauf

```text
Version prüfen
↓
Changelog anzeigen
↓
Backup erstellen
↓
Update herunterladen
↓
Checksumme prüfen
↓
Signatur prüfen
↓
Wartungsmodus aktivieren
↓
Dateien austauschen
↓
Migrationen ausführen
↓
Cache leeren
↓
Rollback-Punkt behalten
```

MD);

    $write($root . '/docs/treeforge/20-installer-concept.md', <<<'MD'
# Installer Concept

TreeForge benötigt einen Installer für neue Installationen.

## Ablauf

```text
Systemcheck
↓
Lizenz / Willkommen
↓
Sprache wählen
↓
Storage wählen
↓
Site-Daten
↓
Admin-User anlegen
↓
Config schreiben
↓
Storage initialisieren
↓
Installation sperren
```

## Systemcheck

- PHP-Version
- benötigte Extensions
- Schreibrechte
- Composer Autoload
- Storage-Verzeichnis
- public/media oder storage/media
- optional SQLite/MySQL Verbindung

## Installation abgeschlossen

Nach Abschluss wird eine Installationssperre geschrieben.

```text
storage/system/installed.lock
```

MD);

    $write($root . '/docs/treeforge/21-roadmap.md', <<<'MD'
# TreeForge Roadmap

## v0.2.x

- Node Creation Wizard
- Markdown
- Columns
- Archive Center
- Archive JSON Export
- Core Foundation
- Master Concept

## v0.3.x

- Settings Foundation
- StorageInterface
- Routing Foundation
- RootNode Page Settings
- SlugManager
- Mini-WAF Foundation

## v0.4.x

- Backend Shell
- Auth/Login
- User Management
- protected API Routes
- Analytics Foundation

## v0.5.x

- Installer Foundation
- SQLite/MySQL Storage
- File to SQL Migration
- Media Foundation

## v0.6.x

- Template System
- Theme Support
- Demo Pages
- Presets
- Global Blocks

## v0.7.x

- Site Package JSON Export/Import
- ZIP Export with Media
- ZIP Import with Media Mapping
- Archive Diff

## v0.8.x

- Update System
- treeforge.de Update API
- Changelog/Checksum/Signature
- Rollback

MD);

    $write($root . '/docs/treeforge/22-routing-concept.md', <<<'MD'
# Routing Concept

TreeForge soll Routing nicht fest an ein Framework binden.

## Grundidee

```text
RouterInterface
├── SimpleRouter
└── optional SlimRouterAdapter
```

TreeForge kann später Slim verwenden, soll aber nicht zwingend davon abhängig sein.

## Request Pipeline

```text
Request
↓
Bootstrap
↓
Mini-WAF
↓
Router
↓
Middleware
↓
Controller
↓
Response
```

## Frontend-Routen

```text
/
 /{slug}
 /{lang}/{slug}
```

## Backend-Routen

```text
/admin
/admin/settings
/explorer
/archives
/media
/templates
/nodes
/updates
```

## API-Routen

```text
/api/node/create
/api/archive/restore
/api/archive/export-json
/api/settings/save
/api/auth/login
/api/auth/logout
```

## SlugResolver

Frontend-Routing nutzt nicht direkt Dateien, sondern einen SlugResolver.

```text
Request /leistungen/webdesign
↓
SlugResolver
↓
content_id
↓
Storage
↓
Renderer
```

MD);

    $write($root . '/docs/treeforge/23-root-node-page-settings.md', <<<'MD'
# RootNode Page Settings

Die RootNode ist nicht nur der Einstiegspunkt des Baums.

Sie enthält auch die Seiteneinstellungen.

## Beispiel

```json
{
  "id": "home",
  "type": "root",
  "content_type": "page",
  "title": "Startseite",
  "slug": "",
  "path": "/",
  "status": "published",
  "lang": "de",
  "template": "default",
  "seo": {
    "meta_title": "TreeForge CMS",
    "meta_description": "Node-basiertes CMS",
    "canonical_url": "",
    "robots": "index,follow"
  },
  "social": {
    "og_title": "",
    "og_description": "",
    "og_image": "",
    "twitter_card": "summary_large_image"
  },
  "overview": {
    "excerpt": "",
    "overview_image": "",
    "featured": false
  },
  "routing": {
    "redirect_from": [],
    "redirect_to": "",
    "is_home": true,
    "no_slug": true
  }
}
```

## Editor Tabs

```text
Seiteneinstellungen
├── Allgemein
├── SEO
├── Social
├── Übersicht
├── Routing
└── Erweitert
```

MD);

    $write($root . '/docs/treeforge/24-content-types-and-root-flags.md', <<<'MD'
# Content Types and Root Flags

Das Node-System bleibt für alle Inhalte gleich.

Die RootNode definiert den Content-Typ.

## Content Types

```text
page
landingpage
blog
news
faq
product
download
event
```

## Flags

```json
{
  "is_home": false,
  "is_archive": false,
  "show_in_menu": true,
  "show_in_sitemap": true,
  "allow_comments": false,
  "is_searchable": true
}
```

## Content-Typ steuert Zusatzfelder

```text
page
- Standard-Seite

landingpage
- optional kein Menü
- eigener Header/Footer möglich

blog
- Autor
- Datum
- Kategorie
- Tags
- Auszug
- Beitragsbild

news
- Datum
- Quelle
- Wichtigkeit
- Ablaufdatum

faq
- Frage
- Kategorie
- Priorität

product
- SKU
- Preis
- Galerie
- Verfügbarkeit
```

## Wichtig

Der Content-Typ verändert nicht das Node-System.

Er erweitert nur RootNode-Metadaten, Routing und Backend-Felder.

MD);

    $write($root . '/docs/treeforge/25-seo-url-slug-manager.md', <<<'MD'
# SEO URL and Slug Manager

TreeForge braucht ein zentrales Modul für SEO-URLs.

## Aufgaben

```text
generateSlug(title)
normalizeSlug(slug)
ensureUniqueSlug(slug, contentId, lang)
resolvePath(path, lang)
validateSlug(slug)
buildRouteIndex()
```

## Slug-Beispiele

```text
Über uns
→ ueber-uns

Leistungen & Webdesign
→ leistungen-webdesign

ueber-uns existiert bereits
→ ueber-uns-2
```

## Eindeutigkeit

Später in SQL:

```sql
UNIQUE(lang, path)
```

Bei FileStorage zunächst:

```text
storage/system/routes.json
```

## routes.json

```json
{
  "/": {
    "content_id": "home",
    "lang": "de"
  },
  "/ueber-uns": {
    "content_id": "about",
    "lang": "de"
  },
  "/kontakt": {
    "content_id": "contact",
    "lang": "de"
  }
}
```

## Ablauf

```text
Content speichern
↓
SlugManager normalisiert Slug
↓
prüft Eindeutigkeit
↓
setzt path
↓
aktualisiert routes.json
↓
Frontend kann URL auflösen
```

MD);

    $log('Patch 035 TreeForge Master Concept fertig');
};
