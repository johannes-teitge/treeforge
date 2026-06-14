# Explorer V2 Cleanup Save Buttons

Patch 112 räumt die Speichern-Buttons im Explorer V2 auf.

## Ziel

- Globaler Button oben bleibt für Page/Global Save.
- Node Properties speichern über klar benannten Button:

```text
Node speichern
```

## Entfernt / ersetzt

```text
Speichern kommt in Patch 110
```

wird zu:

```text
Node speichern
```

## Save-JS

Der Save-Handler akzeptiert nun mehrere Button-Varianten:

```text
#tfv2SaveNodeProperties
[data-node-save]
#tfv2NodeSave
.tfv2-node-save
```