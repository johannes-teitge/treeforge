# Fix SettingsManager Syntax

Patch 047 repariert einen Syntaxfehler in `SettingsManager.php`.

## Ursache

Beim automatischen Einfügen der `geo_blocking` Settings wurde die Array-Struktur in `SettingsManager::defaults()` beschädigt.

## Fehler

```text
Fatal error: Cannot use empty array elements in arrays
```

## Fix

`SettingsManager.php` wurde sauber neu geschrieben.

Enthalten sind jetzt:

- General
- Languages
- Storage
- Editor
- Media
- Image Presets
- Render Cache
- Security
- Geo Blocking
- Analytics
- Updates
- Developer