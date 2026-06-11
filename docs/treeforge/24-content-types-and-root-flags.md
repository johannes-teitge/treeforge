# Content Types and Root Flags

Das Node-System bleibt für alle Inhalte gleich.

Die RootNode definiert den Content-Typ.

## Content Types

```text
page
landingpage
blog
news
faq
product
download
event
```

## Flags

```json
{
  "is_home": false,
  "is_archive": false,
  "show_in_menu": true,
  "show_in_sitemap": true,
  "allow_comments": false,
  "is_searchable": true
}
```

## Content-Typ steuert Zusatzfelder

```text
page
- Standard-Seite

landingpage
- optional kein Menü
- eigener Header/Footer möglich

blog
- Autor
- Datum
- Kategorie
- Tags
- Auszug
- Beitragsbild

news
- Datum
- Quelle
- Wichtigkeit
- Ablaufdatum

faq
- Frage
- Kategorie
- Priorität

product
- SKU
- Preis
- Galerie
- Verfügbarkeit
```

## Wichtig

Der Content-Typ verändert nicht das Node-System.

Er erweitert nur RootNode-Metadaten, Routing und Backend-Felder.
