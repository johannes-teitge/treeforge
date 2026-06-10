# Fix Async Text Save

Patch 017 behebt den alten Reload beim Speichern.

## Wichtig

Wenn nach dem Patch weiterhin die Meldung

```text
Gespeichert. Seite wird neu geladen ...
```

erscheint, lädt der Browser noch ein altes JavaScript.

Dann hart neu laden:

```text
Strg + F5
```

oder prüfen, ob im Quellcode steht:

```html
/assets/js/explorer.js?v=017
```

## Test

```text
/explorer?workspace=draft
```

TextNode wählen, Text ändern, speichern.

Erwartung:

- kein Reload
- Toast "TextNode im Draft gespeichert."
- Fokus bleibt im Textfeld
- `storage/workspaces/draft/pages/home.json` enthält den neuen Text
