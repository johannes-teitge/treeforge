# Collapsible Tree

Patch 022 ergänzt Auf- und Zuklappen im Explorer.

## Funktionen

- Nodes mit Kindern bekommen einen Toggle-Pfeil.
- `▾` bedeutet geöffnet.
- `▸` bedeutet geschlossen.
- Alle aufklappen.
- Alle zuklappen.
- Zustand wird in `localStorage` gespeichert.

## Warum wichtig?

Bei verschachtelten Strukturen wie Columns wird der Explorer sonst schnell unübersichtlich.

```text
Columns
├─ Column
│  ├─ Text
│  └─ Image
└─ Column
   ├─ Text
   └─ Button
```

Zugeklappt:

```text
▸ Columns
```
