# Frontend Node Renderer Foundation

Patch 105 rendert eine erste Website aus JSON.

## Aufruf

```text
/page.php?page=home&workspace=draft
/page.php?page=home&workspace=draft&template=alt
```

## Unterstützt

RootNode, ContainerNode, ScheduleContainerNode, ColumnsNode, ColumnNode, TextNode, MarkdownNode, HtmlNode, ImageNode, ButtonNode, ReferenceNode.

## Properties-ready

Der Renderer liest bevorzugt `properties{}` und nutzt alte Root-Felder als Fallback.