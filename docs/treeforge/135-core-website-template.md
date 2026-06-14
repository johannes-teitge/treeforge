# Patch 135 – Core Website Template

Dieser Patch macht aus dem rohen Core-Twig-Template eine stärker websiteartige Basis.

## Änderungen

- `core/templates/layouts/base.twig` erhält eine echte Struktur:
  - Skip-Link
  - Header
  - Brand
  - optionale Header-Area
  - Main
  - Footer
- `core/templates/layouts/page.twig` erhält einen Page-Intro-Bereich und einen Content-Container.
- `core/templates/assets/css/core-template.css` wird modernisiert.
- Das CSS wird zusätzlich nach `public/assets/treeforge/core/css/core-template.css` kopiert, sofern es sich geändert hat.

## Areas

Header und Footer bleiben dynamisch:

```twig
{{ tf_area('header')|raw }}
{{ tf_area('footer')|raw }}
```

Wenn keine Area existiert oder leer ist, bleibt die Ausgabe leer bzw. der Footer zeigt einen einfachen Fallback.

## Ziel

Der Twig-Renderer soll nun nicht mehr wie ein technischer Test aussehen, sondern als echte Basis für eine Website dienen. Custom Templates kommen später und überschreiben dann gezielt Layouts oder Node-Partials.