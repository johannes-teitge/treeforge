# Explorer V2 Clipboard Paste API Integration

Patch 103 verbindet Clipboard-Einfügen mit der Mutation API.

## Unterstützt

```text
Kopieren
Einfügen
Referenz einfügen
Clipboard leeren
```

## Noch nicht

```text
Ausschneiden / Move
```

## API Actions

```text
paste-copy
paste-reference
```

## Ablauf

```text
Node → Kopieren
Container/Column/Root → Einfügen
POST /api/explorer-v2/mutate.php
JSON speichern
Reload auf draft
```