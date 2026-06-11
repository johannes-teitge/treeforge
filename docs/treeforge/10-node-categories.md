# Node-Kategorien

TreeForge unterscheidet zwischen Core-Nodes, Custom-Nodes und Restricted-Nodes.

Diese Einteilung steuert unter anderem:

- Anzeige im Plus-Menü
- Rechte und Sichtbarkeit
- Sicherheitsbewertung
- Dokumentation
- Wartbarkeit

## Core-Nodes

Core-Nodes sind feste Basis-Bausteine, die TreeForge immer mitbringt.

Sie werden vom TreeForge-Core gepflegt und sollten in fast jedem Projekt verfügbar sein.

```text
Core Nodes
├── Root
├── Section
├── Container
├── Columns
├── Column
├── Heading
├── Text
├── HTML
├── Markdown
├── Image
├── Button
├── Divider
├── Spacer
└── CodeDisplay
```

## Layout-Nodes

Layout-Nodes strukturieren Inhalte.

```text
Layout
├── Section
├── Container
├── Columns
├── Column
├── Spacer
└── Divider
```

## Content-Nodes

Content-Nodes erzeugen sichtbare Inhalte.

```text
Content
├── Heading
├── Text
├── HTML
├── Markdown
├── Image
├── Button
└── CodeDisplay
```

## Custom-Nodes

Custom-Nodes sind projektspezifische oder kundenspezifische Erweiterungen.

Sie können als Drop-in-Ordner unter `custom/nodes/` eingebunden werden.

```text
Custom Nodes
├── Accordion
├── Tabs
├── Gallery
├── Hero
├── Slider
├── FAQ
├── Team
├── GoogleMap
├── BlogList
├── NewsList
├── ProductList
├── DownloadBox
├── ContactBox
└── OpeningHours
```

## Restricted-Nodes

Restricted-Nodes sind Nodes mit erhöhtem Sicherheitsrisiko.

Sie dürfen nur für berechtigte Benutzer sichtbar und nutzbar sein.

```text
Restricted Nodes
├── RawHtml
├── CustomJS
├── PHP
├── System
└── ExternalEmbed
```

## Plus-Menü

Das Plus-Menü sollte Nodes gruppiert anzeigen.

```text
+ Layout
  Section
  Container
  Columns

+ Inhalt
  Heading
  Text
  Image
  Button

+ Medien
  Gallery
  Video

+ Erweiterungen
  Accordion
  Tabs
  FAQ

+ Entwickler
  HTML
  Markdown
  CodeDisplay
```

## Empfehlung

Neue Nodes sollten immer einer Kategorie zugeordnet werden.

Beispiel:

```php
public string $category = 'Content';
```

Oder über `node.json`:

```json
{
  "category": "Layout"
}
```
