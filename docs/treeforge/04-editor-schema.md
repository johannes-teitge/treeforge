# Editor-Schema

Jede Node liefert ihr eigenes Editor-Schema.

Der Editor baut daraus automatisch die rechte Properties-Spalte.

## Unterstützte Feldtypen

- text
- textarea
- richtext
- markdown
- select
- checkbox
- color
- image
- media
- number
- date
- datetime
- repeater

## Beispiel

```php
public function getEditorSchema(): array
{
    return [
        [
            'tab' => 'Inhalt',
            'fields' => [
                [
                    'name' => 'headline',
                    'label' => 'Überschrift',
                    'type' => 'text'
                ],
                [
                    'name' => 'text',
                    'label' => 'Text',
                    'type' => 'textarea'
                ]
            ]
        ]
    ];
}
```

## Standard-Tabs

Empfohlene Tabs:

- Inhalt
- Layout
- Design
- Sichtbarkeit
- Erweitert

## Basisfelder im Tab Sichtbarkeit

Diese Felder kann der Core automatisch ergänzen:

- active
- valid_from
- valid_until

## Basisfelder im Tab Erweitert

Diese Felder kann der Core automatisch ergänzen:

- css_id
- css_class
