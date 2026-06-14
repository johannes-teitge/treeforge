# Explorer V2 Node Type Registry + Add Node Dialog

Patch 087 ergänzt eine erweiterbare Node-Type-Registry und einen Add-Node-Dialog.

## Registry

```js
window.TreeForgeV2NodeTypeRegistry.register({
  type: "GalleryNode",
  label: "Galerie",
  icon: "🖼️",
  group: "Media",
  description: "Bildergalerie",
  defaults: { type: "GalleryNode", children: [] }
});
```

## Dialog

Öffnet über:

```text
⋯ → Hinzufügen
```

## Gruppen

- Content
- Media
- Layout
- Interaction
- Code

## Noch offen

- echte JSON-Mutation
- Speichern
- Parent/Child-Regeln
- Berechtigungen pro Node-Typ