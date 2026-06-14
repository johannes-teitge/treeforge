# Patch 131 – Core Twig Template Foundation

Dieser Patch legt die zentrale Twig-Template-Basis für TreeForge an.

Das Frontend wird dadurch noch nicht automatisch auf Twig umgestellt. Der Patch bereitet nur die Core-Templates vor, damit der spätere `TwigPageRenderer` eine stabile Fallback-Basis hat.

## Ziel

TreeForge soll langfristig so rendern:

```text
Page JSON
→ Node-Daten vorbereiten
→ Template auswählen
→ Layout-Twig rendern
→ Node-Twig-Dateien rendern
→ HTML ausgeben
```

## Core-Pfade

```text
core/templates/core-template.json
core/templates/layouts/base.twig
core/templates/layouts/page.twig
core/templates/layouts/blank.twig
core/templates/nodes/*.twig
core/templates/assets/css/core-template.css
```

## Override-Reihenfolge für später

```text
1. templates/<active-template>/nodes/<type>.twig
2. templates/<parent-template>/nodes/<type>.twig
3. core/templates/nodes/<type>.twig
4. core/templates/nodes/unknown.twig
```

Damit müssen User-Templates nicht jede Core-Node kopieren. Sie überschreiben nur die Dateien, die sie wirklich anders darstellen wollen.

## Layouts

Core bringt zunächst drei Layouts mit:

- `base.twig` – HTML-Grundgerüst
- `page.twig` – normale Inhaltsseite
- `blank.twig` – leeres Layout ohne Header

## Core Node Templates

Aktuell vorhanden:

- `heading.twig`
- `text.twig`
- `image.twig`
- `button.twig`
- `markdown.twig`
- `html.twig`
- `css.twig`
- `codeblock.twig`
- `columns.twig`
- `column.twig`
- `unknown.twig`

## Sicherheitsprinzip

Twig Autoescape bleibt aktiv.

Nur Werte, die serverseitig bereits vorbereitet und geprüft wurden, dürfen mit `|raw` ausgegeben werden, z. B.:

```twig
{{ node.content_html|raw }}
```

Normale Textwerte bleiben escaped:

```twig
{{ node.text }}
```

## Nächster Schritt

Der nächste Patch sollte die Registry/Engine vorbereiten:

```text
Patch 132:
- TemplateRegistry
- CoreTemplateResolver
- TwigPageRenderer
- NodeViewDataBuilder
- noch optional neben HtmlRenderer laufen lassen
```