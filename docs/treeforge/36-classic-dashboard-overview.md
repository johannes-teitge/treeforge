# Classic Dashboard Overview

Patch 046 erweitert das Dashboard im TreeForge Classic Stil.

## Bereiche

```text
Quick Tiles
Webstatistik
Security Overview
Geo-Blocking Planung
Systemstatus
```

## Noch keine Echtzeitdaten

Die Bereiche Webstatistik und Security sind zunächst Platzhalter.

Später werden dort angebunden:

- Analytics Logger
- 404 Monitor
- Mini-WAF
- Security Log
- Geo-Blocking
- Login Protection

## Geo-Blocking Settings

In `settings.json` wird vorbereitet:

```json
{
  "security": {
    "geo_blocking": {
      "enabled": false,
      "mode": "log",
      "allow_countries": ["DE", "AT", "CH"],
      "block_countries": [],
      "log_only_countries": ["CN", "RU", "BY", "KP", "IR"],
      "unknown_country_action": "log"
    }
  }
}
```

## Prinzip

Geo-Blocking soll konfigurierbar sein:

```text
log
challenge
block
```

Nicht hart im Code.