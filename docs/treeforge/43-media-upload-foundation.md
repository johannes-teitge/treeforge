# Media Upload Foundation

Patch 053 ergänzt den ersten echten Upload im Media Manager.

## Route

```text
/admin/media/
```

## Endpoint

```text
/api/media/upload.php
```

## Features

- Drag & Drop Upload
- Mehrfachupload
- Upload per AJAX
- Dateigröße aus Media Settings
- Dateitypen aus Media Settings
- SVG-Regeln
- ZIP/Download-Regeln
- Dateinamen normalisieren
- eindeutige Namen erzeugen
- Meta-Datei automatisch erzeugen

## Speicherung

Bilder:

```text
storage/media/originals/YYYY/MM/
```

Dokumente:

```text
storage/media/originals/documents/YYYY/MM/
```

Downloads:

```text
storage/media/originals/downloads/YYYY/MM/
```

Meta:

```text
storage/media/meta/...datei.ext.json
```

## Noch offen

- echter SVG Sanitizer
- Replace / Versioning
- Media Edit
- Kategorien
- Media Picker
- Render Cache