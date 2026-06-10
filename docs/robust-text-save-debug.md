# Robust Text Save Debug

Patch 019 korrigiert das Speichern der TextNode erneut und gibt Debugdaten zurück.

## API-Antwort enthält jetzt

```json
{
  "saved_file": ".../storage/workspaces/draft/pages/home.json",
  "bytes_written": 1234,
  "verified_content": "..."
}
```

## Test

1. Browser öffnen:
   ```text
   /explorer?workspace=draft
   ```

2. F12 → Netzwerk öffnen.

3. TextNode speichern.

4. Request prüfen:
   ```text
   /api/node/save-text.php
   ```

5. In der Antwort prüfen:
   ```text
   ok: true
   saved_file: richtiger Pfad
   verified_content: neuer Text
   ```
