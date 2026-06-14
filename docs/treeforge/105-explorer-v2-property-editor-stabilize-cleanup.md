# Explorer V2 Property Editor Stabilize Cleanup

Patch 116 ist ein Stabilisierungspatch.

## Warum?

Zu viele kleine Save-Button-Patches haben sich überlagert.

## Bereinigung

- alte Save-Button-Hacks aus `explorer-v2.js` entfernt
- `explorer-v2-property-editor.js` sauber neu geschrieben
- `explorer-v2-save-node-properties.js` sauber neu geschrieben

## Zielzustand

```text
oben: Eigenschaften übernehmen
unten: Abbrechen | Eigenschaften übernehmen
```

Beide Eigenschaften-Buttons speichern die aktuelle Node.