# Force Add Node API Submit

Patch 094 behebt, dass ein ausgewählter Node-Typ nicht eingefügt wurde.

## Fix

Der Button:

```text
Hinzufügen vorbereiten
```

wird robust abgefangen.

Der Patch liest direkt aus dem Dialog:

```text
aktiver Node-Typ
Parent-ID
Defaults aus Registry
```

und sendet an:

```text
/api/explorer-v2/mutate.php
```

## Danach

Die Explorer-Seite wird mit aktuellem Workspace neu geladen.