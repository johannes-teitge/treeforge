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
