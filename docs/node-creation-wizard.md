# Node Creation Wizard

Patch 027 ergänzt das Anlegen neuer Nodes im Explorer.

## Button

```text
+ Node
```

## Node-Typen

```text
Text
Image
Button
Markdown
CSS
Columns
```

## Parent Logik

Wenn keine Node markiert ist, wird die neue Node am Ende der Seite angelegt.

Wenn eine Node markiert ist, wird die neue Node als Child dieser Node angelegt.

## Columns

Bei Columns kann die Spaltenanzahl gewählt werden:

```text
2 bis 6 Spalten
```

TreeForge erzeugt automatisch:

```text
Columns
├─ Column
├─ Column
└─ Column
```

## API

```text
POST /api/node/create.php
```

Beispiel:

```json
{
  "page": "home",
  "parent": "node_column_1",
  "type": "text"
}
```
