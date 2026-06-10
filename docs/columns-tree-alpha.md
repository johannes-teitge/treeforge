# Columns Tree Alpha

Patch 021 ergänzt eine echte verschachtelte Baumstruktur.

## Neue Node-Typen

```text
columns
column
```

## Beispiel

```text
Page
└─ Columns
   ├─ Column
   │  ├─ Text
   │  └─ Image
   └─ Column
      ├─ Text
      └─ Button
```

## Warum wichtig?

Bis hier war TreeForge zwar technisch ein Tree-System, aber die Demo war noch relativ flach.

Mit Columns sieht man erstmals den Unterschied zu klassischen Pagebuildern:

```text
Content ist nicht eine Liste von Blöcken.
Content ist ein strukturierter Baum.
```

## Renderer

`ColumnsNode` rendert ein Grid.

`ColumnNode` rendert eine einzelne Spalte und deren Kind-Nodes.

## Explorer

Der Explorer zeigt die verschachtelte Struktur automatisch, weil er rekursiv arbeitet.
