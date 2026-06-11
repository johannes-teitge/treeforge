# Settings Foundation

Patch 037 ergänzt die erste zentrale Settings-Struktur.

## Dateien

```text
app/Core/Settings/SettingsManager.php
storage/system/settings.json
public/admin/settings/index.php
public/assets/css/settings.css
```

## Route

```text
/admin/settings
```

Falls Rewrite nicht greift:

```text
/admin/settings/index.php
```

## Erste Tabs

```text
General
Languages
Storage
System Info
```

## Zweck

TreeForge bekommt damit eine zentrale Stelle für Systemwerte.

Spätere Bereiche wie Security, Analytics, Updates, Media, Templates und Developer können dort schrittweise ergänzt werden.

## Hinweis

Der Storage-Treiber kann bereits gespeichert werden.

Die aktive Umschaltung auf SQLite/MySQL erfolgt erst mit einem späteren StorageInterface-Patch.