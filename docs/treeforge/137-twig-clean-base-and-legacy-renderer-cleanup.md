# Patch 137 – Twig Clean Base + Legacy Renderer Cleanup

## Ziel

Ab diesem Patch ist Twig die normale Frontend-Basis.

Der alte `HtmlRenderer` wird nicht gelöscht, aber aus dem normalen Rendering-Pfad herausgenommen. Er ist nur noch ein bewusster Fallback:

```text
/?page=impressum&workspace=draft&renderer=legacy
```

Twig ist Standard:

```text
/?page=impressum&workspace=draft
```

Strenger Twig-Test ohne Fallback:

```text
/?page=impressum&workspace=draft&renderer=twig-strict
```

oder:

```bash
set TREEFORGE_RENDERER_STRICT=1
```

## Saubere Core-Basis

Core-Layouts liegen unter:

```text
core/templates/layouts/
```

Core-Node-Templates liegen unter:

```text
core/templates/nodes/
```

Core-CSS liegt als Quelle unter:

```text
core/templates/assets/css/core-template.css
```

und wird öffentlich veröffentlicht nach:

```text
public/assets/treeforge/core/css/core-template.css
```

Der Renderer lädt nur noch die veröffentlichte Asset-URL mit Hash-Parameter.

## Legacy

Der alte `HtmlRenderer` bleibt vorerst erhalten, damit man bei Fehlern vergleichen kann.

Alte öffentliche Core-Template-CSS-Dateien werden nach `storage/legacy/template-assets/` verschoben, sobald die neue Asset-Struktur vorhanden ist.

## Empfehlung

Ab jetzt neue Frontend-Arbeit nur noch an Twig/Core-Templates machen, nicht mehr an `HtmlRenderer.php`.