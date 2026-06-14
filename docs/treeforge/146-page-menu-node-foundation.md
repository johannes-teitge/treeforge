# Patch 146 – PageMenuNode + MenuItemNode Foundation

Patch 146 ergänzt lokale, nodes-driven Menüs direkt im Seitenbaum.

## Neue Node-Typen

- `PageMenuNode` / `pagemenu`
- `MenuItemNode` / `menuitem`

## Warum?

Nicht jedes Menü soll global sein. Für Anker-Menüs, Sidebar-Menüs, Quellenverzeichnisse und kleine Linklisten ist ein lokaler Node viel angenehmer als ein globales Menüsystem.

## Modi

### manual

`PageMenuNode` rendert seine `MenuItemNode`-Kinder.

### headings

`PageMenuNode` sammelt Überschriften der aktuellen Seite und erzeugt daraus Ankerlinks.

Standard-Level:

```text
h2,h3
```

### hybrid

Automatische Überschriften + manuelle MenuItemNodes.

## Ausschlüsse für Heading-Menüs

Ausschlüsse sind auf mehreren Wegen möglich:

- `exclude_heading_ids` im PageMenuNode
- CSS-Klasse an der Überschrift: `no-menu`, `no-pagemenu`, `no-toc`
- später im HeadingNode direkt über `properties.navigation.include_in_page_menu = false`

## Beispiel

```json
{
  "type": "PageMenuNode",
  "properties": {
    "content": {
      "mode": "headings",
      "variant": "sidebar",
      "title": "Auf dieser Seite",
      "heading_levels": "h2,h3"
    }
  }
}
```

## Frontend

Das Core Twig Template rendert PageMenuNode über:

```twig
core/templates/nodes/pagemenu.twig
```