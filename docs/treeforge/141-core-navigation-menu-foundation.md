# Patch 141 – Core Navigation / Menu Foundation

Patch 141 ergänzt eine workspace-basierte Navigation.

## Dateien

```text
storage/workspaces/draft/navigation/main.json
storage/workspaces/draft/navigation/footer.json
storage/workspaces/review/navigation/main.json
storage/workspaces/review/navigation/footer.json
storage/workspaces/published/navigation/main.json
storage/workspaces/published/navigation/footer.json
```

## Twig-Funktionen

```twig
{{ tf_menu('main')|raw }}
{{ tf_menu('footer')|raw }}
```

Prüfung:

```twig
{% if tf_has_menu('main') %}
  {{ tf_menu('main')|raw }}
{% endif %}
```

## Datenstruktur

```json
{
  "id": "main",
  "type": "navigation",
  "title": "Hauptmenü",
  "items": [
    {
      "id": "m_home",
      "label": "Startseite",
      "page": "home",
      "url": "",
      "target": "_self",
      "status": "active",
      "children": []
    }
  ]
}
```

## Page oder URL

Ein Menüpunkt kann entweder auf eine TreeForge-Seite zeigen:

```json
"page": "impressum"
```

oder direkt auf eine URL:

```json
"url": "https://example.com"
```

Wenn `page` gesetzt ist, erzeugt TreeForge automatisch den passenden Link. In Draft/Review wird der Workspace mitgegeben.

## Hinweis

Dies ist die Foundation. Ein visueller Menü-Editor im Backend folgt später.