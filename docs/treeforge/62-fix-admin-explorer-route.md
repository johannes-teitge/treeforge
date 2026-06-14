# Fix Admin Explorer Route

Patch 072 legt eine Admin-Route für den Explorer an.

## Problem

Der Explorer existierte real unter:

```text
/public/explorer/index.php
```

Der Pages Manager verlinkte aber auf:

```text
/admin/explorer/?page=home
```

Das führte zu einem Webserver-404.

## Fix

Neue Bridge:

```text
/public/admin/explorer/index.php
```

Sie lädt denselben ExplorerController.

Damit funktioniert:

```text
/admin/explorer/?page=home
/admin/explorer/?page=kontakt
/admin/explorer/?page=ueber-uns
```