# Page Settings Foundation

Patch 039 ergänzt erste RootNode-/Page-Settings.

## Route

```text
/admin/page-settings/
```

Optional:

```text
/admin/page-settings/?workspace=published&page=home
```

## Tabs

```text
General
SEO
Social
Overview
Routing
Visibility
Advanced
```

## Speicherung

Die Daten werden direkt in der jeweiligen Page-JSON gespeichert:

```text
storage/workspaces/{workspace}/pages/{page}.json
```

## Sichtbarkeit

Die RootNode unterstützt jetzt:

```text
active
valid_from
valid_until
schedule_enabled
schedule.days
schedule.time_from
schedule.time_until
schedule.timezone
outside_schedule
```

Damit sind spätere Szenarien möglich:

- Aktionsseiten
- Öffnungszeiten-Hinweise
- Wartungsmeldungen
- zeitlich begrenzte Landingpages
- Tagesangebote
- geplante Veröffentlichungen

## Hinweis

SlugManager und echtes Frontend-Routing werden erst in späteren Patches aktiv.