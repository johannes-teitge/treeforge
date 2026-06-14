# Frontend Renderer Page Root Alias Fix

Patch 106 behebt:

```text
Unbekannte Node: page
```

## Ursache

Bestehende Page-JSON-Dateien verwenden als Root-Typ:

```json
{
  "type": "page"
}
```

Der Renderer kannte bisher nur:

```text
RootNode
root
```

## Fix

Folgende Root-Typen werden jetzt als RootNode behandelt:

```text
root
rootnode
page
pagenode
```

Zusätzlich werden Root-Children aus diesen Feldern gelesen:

```text
children
nodes
content
```