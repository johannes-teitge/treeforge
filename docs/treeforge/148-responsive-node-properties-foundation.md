# Patch 148 – Responsive Node Properties Foundation

Dieser Patch ergänzt die Grundlage für responsive Node-Properties.

## Neue Properties-Gruppen

```json
{
  "properties": {
    "visibility": {
      "desktop": "1",
      "tablet": "1",
      "mobile": "0"
    },
    "responsive": {
      "tablet": {
        "layout": {
          "width": "100%"
        }
      },
      "mobile": {
        "spacing": {
          "padding": ".75rem"
        }
      }
    }
  }
}
```

## Regel

- Basis-Properties gelten zuerst.
- Responsive-Properties überschreiben nur pro Gerät.
- Sichtbarkeit wird als Media Query gerendert.

## Breakpoints

```css
Desktop: min-width 1024px
Tablet:  768px bis 1023px
Mobile:  max-width 767px
```

## Ausgabe

Beispiel:

```json
"layout": { "width": "320px" },
"responsive": {
  "mobile": {
    "layout": { "width": "100%" }
  }
}
```

wird zu:

```css
#tf-n-abc123 {
  width: 320px;
}

@media (max-width: 767px) {
  #tf-n-abc123 {
    width: 100%;
  }
}
```

## Hinweis

Der CSS-Collector gibt die Regeln aktuell noch im Twig-Layout als `collected_css` aus.
Später kann daraus eine generierte Page-CSS-Datei werden:

```text
public/assets/generated/{workspace}/pages/{page}.css
```