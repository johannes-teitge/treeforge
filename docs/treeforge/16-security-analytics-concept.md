# Security & Analytics Concept

TreeForge soll früh eine kleine Security-Schicht bekommen.

## Request Pipeline

```text
Request
↓
Bootstrap
↓
Mini-WAF
↓
Rate Limit
↓
Security Log
↓
Router
```

## Mini-WAF

Der Mini-WAF soll einfache Angriffe und Bot-Probes blockieren.

Beispiele:

```text
/wp-admin
/wp-login.php
/xmlrpc.php
/.env
/composer.json
/vendor/
/../
php://
base64_decode
eval(
UNION SELECT
<script
```

## Lokale Analytics

TreeForge soll eine kleine lokale Statistik besitzen.

Keine externen Tracker.

```text
tf_visits
- id
- created_at
- ip_hash
- user_agent_hash
- path
- referrer
- method
- status_code
- bot_score
```

## Datenschutz

- IP nicht im Klartext speichern
- IP hashen/anonymisieren
- Do-Not-Track respektieren
- Retention-Tage einstellbar
- Analytics abschaltbar
