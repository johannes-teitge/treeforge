# Patch 126 – Frontend Rendering für HeadingNode und CodeBlockNode

Dieser Patch repariert den Frontend-Fehler:

```text
Unknown node type: HeadingNode
```

## Ursache

Patch 124/125 hatten die neuen Node-Typen im Explorer V2 und Editor verfügbar gemacht. Das klassische Frontend-Rendering über `NodeFactory`, `NodeRegistry` und `HtmlRenderer` kannte diese Typen aber noch nicht.

## Ergänzt

- `TreeForge\Nodes\HeadingNode`
- `TreeForge\Nodes\CodeBlockNode`
- Registrierung in `app/Core/bootstrap.php`
- Rendering in `app/Renderer/HtmlRenderer.php`
- kleine Frontend-CSS-Ergänzung

## Alias-Regeln

Registriert werden jeweils beide Formen:

- `heading`
- `HeadingNode`
- `codeblock`
- `CodeBlockNode`

Damit funktionieren Legacy- und V2-Daten parallel.