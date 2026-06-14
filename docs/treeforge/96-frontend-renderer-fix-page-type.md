# Frontend Renderer Fix Page Type

Patch 107 behebt weiterhin auftretende Ausgabe:

```text
Unbekannte Node: page
```

## Fix

In der aktuellen `NodeRenderer::type()`-Methode werden nun als RootNode behandelt:

```text
root
rootnode
page
pagenode
```

Zusätzlich wird `CssNode` im Frontend vorerst nicht als Unknown ausgegeben.