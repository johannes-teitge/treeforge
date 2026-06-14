# Patch 139 – Core Website Styling Polish

Patch 139 stabilisiert das Twig-Core-Website-Template optisch.

## Ziele

- Twig-Core-Frontend wirkt wie eine echte Website-Basis.
- Header, Main, Footer und Content bekommen klare Struktur.
- TextNode, HeadingNode, ButtonNode, ImageNode, Columns und CodeBlockNode bekommen sinnvolle Standardstyles.
- Die Preview/Admin-Bar bleibt sichtbar und wird optisch integriert.
- Core-CSS bleibt Quelle unter `core/templates/assets/css/core-template.css`.
- Die öffentliche Kopie wird nur aktualisiert, wenn der Inhalt geändert wurde.

## Dateien

```text
core/templates/layouts/base.twig
core/templates/layouts/page.twig
core/templates/layouts/blank.twig
core/templates/assets/css/core-template.css
public/assets/treeforge/core/css/core-template.css
```

## Wichtig

Dieser Patch ändert nur das Core-Template. Custom Templates kommen später und überschreiben nur gezielte Twig-Dateien oder Assets.