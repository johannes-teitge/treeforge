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
