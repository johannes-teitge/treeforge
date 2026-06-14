# Patch 128 – Frontend HtmlNode + Unknown Node Guard

Dieser Patch repariert Frontend-Fehler wie:

```text
Unknown node type: HtmlNode
```

Zusätzlich verhindert er, dass weitere unbekannte Node-Typen beim Testen die komplette Seite mit Fatal Error abbrechen.

## Ergänzt

- `HtmlNode` → rendert echten HTML-Inhalt
- `JavaScriptNode` → rendert Script-Inhalt
- `UnknownNode` → Sicherheitsnetz für unbekannte Typen
- `NodeFactory` fällt bei unbekannten Typen auf `UnknownNode` zurück
- `NodeRegistry` kennt mehr Aliase
- `tools/normalize-node-types.php` normalisiert jetzt auch:
  - `HtmlNode` → `html`
  - `JavaScriptNode`, `JsNode`, `ScriptNode` → `javascript`

## Hinweis

`HtmlNode` und `JavaScriptNode` geben Code im Frontend aus. Das ist für vertrauenswürdige Admins okay, sollte später aber über Rechte/Settings abgesichert werden.

## Normalisierung

Dry Run:

```bash
php tools/normalize-node-types.php --dry-run
```

Nur Impressum im Draft:

```bash
php tools/normalize-node-types.php --workspace=draft --page=impressum --dry-run
php tools/normalize-node-types.php --workspace=draft --page=impressum
```