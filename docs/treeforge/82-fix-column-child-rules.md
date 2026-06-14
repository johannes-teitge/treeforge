# Fix Column Child Rules

Patch 093 behebt das Einfügen von Nodes in vorhandene Columns.

## Problem

Alte oder anders benannte Spalten können heißen:

```text
Column
column
col
ColumnNode
```

Der MutationService erlaubte aber nur exakt:

```text
ColumnNode
```

## Fix

Typ-Aliase werden vor der Parent/Child-Prüfung normalisiert.

## Ergebnis

Einfügen in Columns funktioniert mit:

```text
TextNode
ImageNode
ButtonNode
ContainerNode
ScheduleContainerNode
...
```