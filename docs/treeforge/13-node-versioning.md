# Node-Versionierung

Jede Node sollte eine eigene Version besitzen.

Das erlaubt spätere Änderungen an Datenstruktur, Editor-Schema oder Rendering, ohne alte Inhalte zu zerstören.

## Basis

```php
public string $version = '1.0.0';
```

Oder in `node.json`:

```json
{
  "version": "1.0.0"
}
```

## Warum Versionierung?

Eine Node kann später erweitert werden.

Beispiel `HeroNode` Version 1.0.0:

```json
{
  "headline": "Willkommen",
  "image": "/media/hero.jpg"
}
```

Version 2.0.0 ergänzt Subheadline und Button:

```json
{
  "headline": "Willkommen",
  "subheadline": "Mehr erfahren",
  "image": "/media/hero.jpg",
  "button_text": "Starten",
  "button_link": "/start"
}
```

## Gespeicherte Node-Version

Beim Speichern sollte die aktuell verwendete Node-Version mit gespeichert werden.

```json
{
  "id": 42,
  "type": "hero",
  "node_version": "1.0.0",
  "data": {
    "headline": "Willkommen",
    "image": "/media/hero.jpg"
  }
}
```

## Migrationen

Später kann TreeForge Migrationen anbieten.

```php
public function migrate(array $data, string $fromVersion, string $toVersion): array
{
    if ($fromVersion === '1.0.0' && $toVersion === '2.0.0') {
        $data['subheadline'] = '';
        $data['button_text'] = '';
        $data['button_link'] = '';
    }

    return $data;
}
```

## Empfehlung

Für TreeForge 1.0 genügt zunächst:

- Node-Version definieren
- Node-Version beim Speichern mitführen
- Migrationen dokumentieren, aber noch nicht zwingend vollständig implementieren

So bleibt das System später erweiterbar.
