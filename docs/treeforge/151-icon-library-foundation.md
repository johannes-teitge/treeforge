# Patch 151 – Icon Library Foundation

Patch 151 ergänzt eine zentrale Icon-Grundlage für TreeForge.

## Ziel

MenuItems und globale Menüs sollen Icons verwenden können, ohne HTML in Textfelder zu schreiben.

## Konfiguration

Die Konfiguration liegt unter:

```text
storage/system/icon-libraries.json
```

Standardmäßig sind Bootstrap Icons und Font Awesome Free vorbereitet.

## Schreibweisen

Im Icon-Feld können Redakteure z. B. schreiben:

```text
bi:house
bi:envelope
bi:box-arrow-up-right
fa:house
far:user
fab:github
```

Oder direkte Klassen:

```text
bi bi-house
fa-solid fa-house
fa-brands fa-github
```

## Twig

Core-Templates können Icons rendern mit:

```twig
{{ tf_icon('bi:house')|raw }}
```

## Sicherheit

Das Icon-Feld wird nicht als HTML ausgegeben. Es werden nur sichere CSS-Klassen erzeugt oder ein escaped Text-/Emoji-Fallback gerendert.

## Hinweis

TreeForge speichert keine Fontdateien im Projekt. Die CSS-URLs können in `storage/system/icon-libraries.json` auf eigene lokale Assets umgestellt werden.