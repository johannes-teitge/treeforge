# Patch 142 – Force Twig Menu Renderer Repair

## Problem

Nach Patch 141 konnte das Frontend wieder wie der alte `HtmlRenderer` aussehen.
Das passiert, wenn Twig wegen einer fehlenden Funktion wie `tf_menu()` fehlschlägt und `public/index.php` still auf Legacy zurückfällt.

## Lösung

- `TwigPageRenderer` wurde als robuste Basis neu geschrieben.
- `tf_menu()` und `tf_has_menu()` werden sicher registriert.
- `tf_area()` und `tf_has_area()` bleiben erhalten.
- `public/index.php` nutzt Twig als Standard.
- Legacy ist nur noch bewusst per `?renderer=legacy` erreichbar.
- `base.twig` enthält einen Marker: `TF_CORE_LAYOUT_PATCH_142`.
- Core-CSS wurde neu veröffentlicht.

## Test

```text
/?page=impressum&workspace=draft
/?page=impressum&workspace=draft&renderer=twig-strict
/?page=impressum&workspace=draft&renderer=legacy
```

Wenn wieder das alte Layout erscheint, im HTML-Quelltext nachsehen:

```text
TreeForge Twig fallback:
```

Dann zeigt der Kommentar die eigentliche Twig-Fehlermeldung.