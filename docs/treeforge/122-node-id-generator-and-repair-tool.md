# Patch 122 – Node ID Generator und Repair Tool

Dieser Patch führt kurze, stabile interne Node-IDs ein.

## Format

```text
n_ + 16 Hex-Zeichen
```

Beispiel:

```text
n_4f91a7c02e3bd8aa
```

## Regeln

- `node.id` ist die interne Identität einer Node.
- `properties.advanced.css_id` ist nur eine optionale DOM-/CSS-ID und ändert nicht die interne Node-ID.
- Root-/Page-IDs wie `home` bleiben erhalten.
- Beim Erstellen neuer Nodes wird gegen vorhandene IDs geprüft.
- Beim Duplizieren bekommen echte Nodes neue IDs.
- Referenz-Nodes behalten ihren Verweis über `target_id`/`source_node_id`.

## Repair Tool

Vorher Testlauf:

```bash
php tools/repair-node-ids.php --dry-run
```

Dann echte Reparatur:

```bash
php tools/repair-node-ids.php
```

Nur Draft reparieren:

```bash
php tools/repair-node-ids.php --workspace=draft
```

Nur eine Seite reparieren:

```bash
php tools/repair-node-ids.php --page=home
```

Das Tool legt Backups mit `.bak-node-ids-YYYYmmdd-His` an.