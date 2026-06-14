# Patch 130 - TextNode Paragraph Rendering Repair

Dieser Patch repariert das Frontend-Rendering der TextNode.

## Regel

```text
Leerzeile = neuer Absatz <p>
einfacher Zeilenumbruch = <br> innerhalb des Absatzes
```

## Beispiel Eingabe

```text
Angaben gemäß § 5 DDG

Max Mustermann
Musterfirma
Musterstraße 12
12345 Musterstadt
Deutschland
```

## Ausgabe

```html
<div id="tf-n-..." class="tf-node tf-node-text">
  <p>Angaben gemäß § 5 DDG</p>
  <p>Max Mustermann<br>
Musterfirma<br>
Musterstraße 12<br>
12345 Musterstadt<br>
Deutschland</p>
</div>
```

HTML im TextNode wird escaped. Echter HTML-Code gehört in den HtmlNode.