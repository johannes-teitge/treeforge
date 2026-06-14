# Patch 149 – PageMenuNode Varianten und MenuItem-Felder

Patch 149 erweitert lokale, nodes-driven Menüs.

## PageMenuNode

Neue/erweiterte Optionen:

- `mode`: `manual`, `headings`, `hybrid`
- `variant`: `vertical`, `horizontal`, `buttons`, `pills`, `sources`, `compact`
- `behavior`: `static`, `sticky`, `popup`, `dropdown`
- `show_title`: blendet nur den Menütitel aus, nicht die Items
- `button_label`: Text für Popup/Dropdown
- `button_icon`: Icon für Popup/Dropdown
- `active_mode`: vorbereitet für `current_url` und `scrollspy`
- `empty_message`: Meldung bei leerem Menü

## MenuItemNode

Neue Felder:

- `icon`
- `badge`
- `rel`
- `aria_label`
- `item_type`: `link`, `anchor`, `button`, `download`, `source`

## Varianten

### Vertical

Klassisches Seitenmenü / Sidebar.

### Horizontal

Horizontale Linkleiste.

### Buttons

Alle Items werden wie Buttons dargestellt.

### Pills

Kompakte Chips/Tabs.

### Sources

Für Quellen- und Literaturverweise.

### Compact

Kleine, platzsparende Linkliste.

## Popup / Dropdown

`behavior=popup` und `behavior=dropdown` nutzen zunächst native HTML-Details/Summary. Ein JavaScript-Upgrade für Escape-Taste, Klick-außerhalb und ARIA kann später folgen.