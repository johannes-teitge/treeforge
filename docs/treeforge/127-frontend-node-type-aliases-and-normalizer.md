# Patch 127 – Frontend Node-Type Aliases + Normalizer

Dieser Patch repariert Frontend-Fehler wie:

```text
Unknown node type: TextNode
Unknown node type: HeadingNode
Unknown node type: CodeBlockNode
```

## Ursache

Der Explorer V2 kann neue Nodes mit Klassen-/UI-Namen speichern, z. B. `TextNode` oder `HeadingNode`.
Das klassische Frontend erwartet aber kanonische Typen wie `text`, `heading`, `codeblock`.

## Lösung

- `NodeRegistry` löst Aliase robust auf.
- `bootstrap.php` registriert zusätzlich bekannte Alias-Typen.
- `tools/normalize-node-types.php` kann bestehende JSON-Dateien normalisieren.

## Normalisierung

Dry Run:

```bash
php tools/normalize-node-types.php --dry-run
```

Nur Draft/Home:

```bash
php tools/normalize-node-types.php --workspace=draft --page=home --dry-run
php tools/normalize-node-types.php --workspace=draft --page=home
```

Typ-Mapping:

- `TextNode` → `text`
- `HeadingNode` → `heading`
- `CodeBlockNode` → `codeblock`
- `ImageNode` → `image`
- `ButtonNode` → `button`
- `ColumnsNode` → `columns`
- `ColumnNode` → `column`
- `CssNode` → `css`
- `MarkdownNode` → `markdown`