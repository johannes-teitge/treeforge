# BaseContainerNode Foundation

Patch 090 führt das BaseContainerNode-Konzept ein.

## Idee

Kleine Content-Nodes bleiben schlank:

```text
TextNode = Text
ImageNode = Bild
ButtonNode = Button
```

Layout, Abstände und Design gehören in Container:

```text
ContainerNode
  ├ TextNode
  ├ ImageNode
  └ ButtonNode
```

## Neuer Typ

```text
ContainerNode
```

Gruppe:

```text
Container
```

## Container-Felder

```json
{
  "container": {
    "display": "block",
    "width": "",
    "max_width": "",
    "min_height": "",
    "margin": "",
    "padding": "",
    "gap": "",
    "background": "",
    "border": "",
    "border_radius": "",
    "box_shadow": "",
    "css_class": "",
    "css_id": "",
    "custom_style": ""
  }
}
```

## ScheduleContainerNode

Der ScheduleContainerNode bekommt dieselben Container-Felder plus:

```json
{
  "schedule": {}
}
```

## Spätere Ableitungen

```text
SectionNode
CardNode
AccordionNode
TabsNode
ScheduleContainerNode
```