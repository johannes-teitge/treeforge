# Fix Wizard Hidden Columns Options

Patch 030 behebt, dass die Columns-Optionen bei jedem Node-Typ sichtbar waren.

## Ursache

Die Klasse `.tf-form-row` setzt `display: grid`.

Dadurch konnte das `hidden`-Attribut optisch übersteuert werden.

## Fix

```css
.tf-form-row[hidden],
.tf-modal [hidden] {
  display: none !important;
}
```

Zusätzlich setzt JS nun explizit:

```js
columnsOptions.style.display = isColumns ? 'grid' : 'none';
```
