# SEO URL and Slug Manager

TreeForge braucht ein zentrales Modul für SEO-URLs.

## Aufgaben

```text
generateSlug(title)
normalizeSlug(slug)
ensureUniqueSlug(slug, contentId, lang)
resolvePath(path, lang)
validateSlug(slug)
buildRouteIndex()
```

## Slug-Beispiele

```text
Über uns
→ ueber-uns

Leistungen & Webdesign
→ leistungen-webdesign

ueber-uns existiert bereits
→ ueber-uns-2
```

## Eindeutigkeit

Später in SQL:

```sql
UNIQUE(lang, path)
```

Bei FileStorage zunächst:

```text
storage/system/routes.json
```

## routes.json

```json
{
  "/": {
    "content_id": "home",
    "lang": "de"
  },
  "/ueber-uns": {
    "content_id": "about",
    "lang": "de"
  },
  "/kontakt": {
    "content_id": "contact",
    "lang": "de"
  }
}
```

## Ablauf

```text
Content speichern
↓
SlugManager normalisiert Slug
↓
prüft Eindeutigkeit
↓
setzt path
↓
aktualisiert routes.json
↓
Frontend kann URL auflösen
```
