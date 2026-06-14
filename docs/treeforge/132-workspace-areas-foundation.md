# Patch 132: Workspace Areas Foundation

Globale Bereiche sind wiederverwendbare Inhaltsbereiche für Templates.

## Speicherorte

```text
storage/workspaces/draft/areas/footer.json
storage/workspaces/review/areas/footer.json
storage/workspaces/published/areas/footer.json
```

## Unterschied Page vs Area

Page:
- hat URL
- hat SEO
- erscheint in Seitenübersicht und Navigation

Area:
- hat keine eigene URL
- wird in Templates eingebunden
- Beispiele: Header, Footer, Sidebar, CTA, Cookie-Hinweis

## Explorer V2

Pages:

```text
/admin/explorer-v2/?page=impressum&workspace=draft
```

Areas:

```text
/admin/explorer-v2/?area=footer&workspace=draft
```

## Späterer Twig-Aufruf

```twig
{{ tf_area('footer')|raw }}
```

Dieser Patch legt die Datenstruktur und Bearbeitung an. Die Twig-Funktion wird in einem Folgepatch implementiert.