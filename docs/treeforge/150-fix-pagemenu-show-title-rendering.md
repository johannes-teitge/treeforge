# Patch 150 – PageMenu show_title Rendering Fix

## Problem

Die Einstellung im PageMenuNode:

```text
Titel anzeigen: Nein – nur Menüpunkte anzeigen
```

wurde gespeichert, aber im Frontend wurde der Menütitel trotzdem gerendert.

Ursache war Twig:

```twig
node.show_title|default(true)
```

Der `default`-Filter behandelt `false`, `0` oder `'0'` als leer und setzt dadurch wieder `true` ein.

## Lösung

`pagemenu.twig` normalisiert den Wert jetzt explizit:

```twig
{% set rawShowTitle = node.show_title is defined ? node.show_title : true %}
{% set showTitle = rawShowTitle not in [false, 0, '0', 'false', 'no', 'nein', 'off', 'hidden', 'hide'] %}
```

Dadurch wird bei `show_title = 0` nur der Titel ausgeblendet. Die MenuItems bleiben sichtbar.