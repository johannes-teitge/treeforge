# RootNode Page Settings

Die RootNode ist nicht nur der Einstiegspunkt des Baums.

Sie enthält auch die Seiteneinstellungen.

## Beispiel

```json
{
  "id": "home",
  "type": "root",
  "content_type": "page",
  "title": "Startseite",
  "slug": "",
  "path": "/",
  "status": "published",
  "lang": "de",
  "template": "default",
  "seo": {
    "meta_title": "TreeForge CMS",
    "meta_description": "Node-basiertes CMS",
    "canonical_url": "",
    "robots": "index,follow"
  },
  "social": {
    "og_title": "",
    "og_description": "",
    "og_image": "",
    "twitter_card": "summary_large_image"
  },
  "overview": {
    "excerpt": "",
    "overview_image": "",
    "featured": false
  },
  "routing": {
    "redirect_from": [],
    "redirect_to": "",
    "is_home": true,
    "no_slug": true
  }
}
```

## Editor Tabs

```text
Seiteneinstellungen
├── Allgemein
├── SEO
├── Social
├── Übersicht
├── Routing
└── Erweitert
```
