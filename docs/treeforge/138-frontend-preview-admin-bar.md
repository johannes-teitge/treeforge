# Patch 138 – Frontend Preview / Admin Bar

## Ziel

Das Twig-Frontend zeigt bei Draft/Review eine kleine Vorschau-Leiste.

Sie zeigt:

- Workspace
- Page-ID
- Renderer
- Link zum Explorer V2
- Link zur Seitenübersicht
- Link zu Twig Strict
- Link zum Legacy Renderer
- Link zur Published-Ansicht

## Sichtbarkeit

Die Leiste ist sichtbar bei:

```text
workspace=draft
workspace=review
?preview=1
```

Bei normaler Published-Ausgabe bleibt sie verborgen.

## Test

```text
/?page=impressum&workspace=draft
/?page=impressum&workspace=draft&renderer=twig-strict
/?page=impressum&workspace=draft&renderer=legacy
```

## Wichtig

Der alte `HtmlRenderer` wird dadurch nicht verändert. Die Preview-Bar gehört zur neuen Twig-Basis.