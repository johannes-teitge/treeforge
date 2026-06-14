# Patch 133: Twig Renderer + `tf_area()`

Dieser Patch ergänzt den ersten optionalen Twig-Frontend-Renderer.

## Installation

Der Patch trägt `twig/twig` in `composer.json` ein.

Danach ausführen:

```bash
composer update twig/twig
```

Alternativ:

```bash
composer require twig/twig:^3.0
```

## Testaufruf

Der alte `HtmlRenderer` bleibt Standard.

Twig testweise aktivieren:

```text
/?page=impressum&workspace=draft&renderer=twig
```

Oder per Umgebung:

```bash
set TREEFORGE_RENDERER=twig
```

## Globale Areas

Patch 132 hat Areas eingeführt:

```text
storage/workspaces/draft/areas/footer.json
storage/workspaces/draft/areas/header.json
```

Patch 133 macht sie in Twig verfügbar:

```twig
{{ tf_area('footer')|raw }}
```

Prüfen, ob eine Area Inhalt hat:

```twig
{% if tf_has_area('footer') %}
  <footer>
    {{ tf_area('footer')|raw }}
  </footer>
{% endif %}
```

## Core base.twig

`core/templates/layouts/base.twig` bindet jetzt automatisch ein:

```twig
{{ tf_area('header')|raw }}
{{ tf_area('footer')|raw }}
```

Nur wenn die Area existiert und Children enthält.

## Sicherheitsregel

`tf_area()` rendert nur Nodes aus der Area. Direkte Dateizugriffe im Twig-Template sind nicht vorgesehen.

Der Renderer schützt vor rekursiven Area-Aufrufen.