# Media Drag & Drop Categories

Patch 065 ergänzt Drag & Drop für Media-Kategorien.

## Verhalten

```text
Medienkarte greifen
→ auf Kategorie links ziehen
→ Kategorie wird in Meta JSON gespeichert
→ Seite lädt neu
```

## Endpoint

```text
/api/media/set-category.php
```

## Drop-Ziele

```text
Nicht einsortiert
alle Kategorien
```

## Speichert in

```json
{
  "category": "blog"
}
```