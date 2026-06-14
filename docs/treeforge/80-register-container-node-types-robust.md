# Register Container Node Types Robust

Patch 091 registriert Container-Typen robust im Explorer V2.

## Problem

Patch 090 hatte die JS-Registry nicht zuverlässig getroffen.

## Fix

Am Ende von:

```text
public/assets/js/explorer-v2-node-types.js
```

werden sicher registriert:

```text
ContainerNode
ScheduleContainerNode
```

## Gruppe

```text
Container
```

## Vorteil

Keine fragile String-Ersetzung mehr.