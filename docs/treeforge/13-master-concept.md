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
