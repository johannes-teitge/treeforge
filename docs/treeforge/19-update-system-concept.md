# Update System Concept

TreeForge soll später Updates über treeforge.de ausliefern können.

## Update API

```text
treeforge.de/api/updates
```

liefert:

```json
{
  "latest": "0.4.0-alpha",
  "channel": "alpha",
  "download_url": "",
  "checksum": "",
  "signature": "",
  "changelog": []
}
```

## Ablauf

```text
Version prüfen
↓
Changelog anzeigen
↓
Backup erstellen
↓
Update herunterladen
↓
Checksumme prüfen
↓
Signatur prüfen
↓
Wartungsmodus aktivieren
↓
Dateien austauschen
↓
Migrationen ausführen
↓
Cache leeren
↓
Rollback-Punkt behalten
```
