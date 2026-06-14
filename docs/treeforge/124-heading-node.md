# Patch 124 – HeadingNode / Überschrift

Dieser Patch ergänzt einen eigenen Node-Typ für Überschriften.

## Warum?

Normale Benutzer sollen keine HTML-Tags wie `<h2>` schreiben müssen. Eine Überschrift ist ein eigener Inhaltsbaustein.

## Node-Typen

- `HeadingNode` im Explorer V2
- `heading` im Legacy NodeCreator

## Eigenschaften

```json
{
  "type": "HeadingNode",
  "properties": {
    "content": {
      "text": "Meine Überschrift",
      "level": "h2"
    }
  }
}
```

## H1-Regel

TreeForge verbietet H1 nicht hart. Später soll der SEO-/Accessibility-Check warnen, wenn eine Seite mehrere H1-Überschriften enthält.

## Page-Titel vs. Überschrift

- Page-Titel: Admin, Navigation, SEO-Metadaten
- HeadingNode: sichtbarer Inhalt auf der Seite