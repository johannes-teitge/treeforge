# Patch 134: Template Asset Publisher

Dieser Patch ergänzt einen Publisher für Template-Assets.

## Warum?

`core/templates/assets/...` ist Quellcode und sollte nicht direkt vom Browser geladen werden.
Der Browser lädt nur Dateien aus `public/`.

## Core CSS

Quelle:

```text
core/templates/assets/css/core-template.css
```

Öffentliche Kopie:

```text
public/assets/treeforge/core/css/core-template.css
```

Öffentliche URL:

```text
/assets/treeforge/core/css/core-template.css?v=<hash>
```

## Kopierregel

Die Datei wird nur kopiert, wenn:

- das Ziel fehlt
- oder sich der SHA1-Hash der Quelle vom Ziel unterscheidet

Dadurch kann der Renderer den Publisher aufrufen, ohne bei jedem Request unnötig zu schreiben.

## Manuell veröffentlichen

```bash
php tools/publish-template-assets.php
```

Später für Custom Templates:

```bash
php tools/publish-template-assets.php --template=default
```

## Zukünftige Struktur

Core:

```text
core/templates/assets/css/core-template.css
public/assets/treeforge/core/css/core-template.css
```

Custom Template:

```text
templates/<id>/assets/css/template.css
public/assets/treeforge/templates/<id>/css/template.css
```