# Patch 147 – Node CSS Collector Foundation

Dieser Patch rendert die Standard-Properties einer Node erstmals im Frontend als CSS.

## Unterstützte Property-Gruppen

```json
{
  "properties": {
    "layout": {
      "display": "block",
      "alignment": "left",
      "width": "320px",
      "max_width": "100%",
      "min_height": ""
    },
    "spacing": {
      "margin": "0 0 2rem",
      "padding": "1rem",
      "gap": "1rem"
    },
    "design": {
      "background": "#fff",
      "color": "#1E3D1C",
      "border": "1px solid #ddd",
      "border_radius": "1rem",
      "box_shadow": "0 10px 30px rgba(0,0,0,.08)"
    },
    "advanced": {
      "css_id": "kontakt",
      "css_class": "my-box",
      "custom_style": "font-weight: 700;"
    },
    "custom_css": "& strong { color: #D88A22; }"
  }
}
```

## Ausgabe

Aktuell wird das CSS gesammelt und im Twig-Layout über das bereits vorhandene Feld `collected_css` ausgegeben:

```html
<style id="tf-collected-css">
#tf-n-abc123 {
  width: 320px;
  padding: 1rem;
}
</style>
```

Das ist bewusst die Foundation. Ein späterer Patch kann daraus eine generierte Datei machen:

```text
public/assets/generated/{workspace}/pages/{page}.css
```

## Custom CSS

Einfache Deklarationen:

```css
color: red;
font-weight: bold;
```

werden zu:

```css
#tf-n-abc123 {
  color: red;
  font-weight: bold;
}
```

Mit `&` kann explizit auf die aktuelle Node verwiesen werden:

```css
& strong {
  color: #D88A22;
}
```

wird zu:

```css
#tf-n-abc123 strong {
  color: #D88A22;
}
```