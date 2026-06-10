# Review Workflow

Patch 014 ergänzt einen einfachen Review-Workflow.

## Ablauf

```text
Draft
  ↓ In Review senden
Review
  ↓ Freigeben & veröffentlichen
Published
```

Optional:

```text
Review
  ↓ Zurück an Draft
Draft
```

## API

```text
POST /api/workflow/action.php
```

Payload:

```json
{
  "action": "send_to_review",
  "page": "home"
}
```

Aktionen:

```text
send_to_review
return_to_draft
publish_review
```

## Dateien

```text
draft/pages/home.json
review/pages/home.json
published/pages/home.json
archive/YYYY-MM-DD-HHMMSS/home.json
```

## Wichtig

Bearbeitet wird weiterhin nur im Draft Workspace.

Review ist readonly.

Published ist live.
