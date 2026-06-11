# DemoNode

Die DemoNode dient als Entwickler-Tutorial.

Sie zeigt:

- Metadaten
- Default-Daten
- Editor-Schema
- Rendering
- Assets
- Children-Regeln

## Beispiel

```php
class DemoNode extends AbstractTreeForgeNode
{
    public string $type = 'demo';
    public string $label = 'Demo Node';
    public string $icon = 'fa-solid fa-puzzle-piece';
    public string $category = 'Demo';

    public bool $hasChildren = false;

    public function getDefaultData(): array
    {
        return [
            'headline' => 'Demo Überschrift',
            'text' => 'Demo Text',
            'style' => 'info',
            'show_icon' => true
        ];
    }

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
            ],
            [
                'tab' => 'Design',
                'fields' => [
                    [
                        'name' => 'style',
                        'label' => 'Darstellung',
                        'type' => 'select',
                        'options' => [
                            'info' => 'Info',
                            'success' => 'Erfolg',
                            'warning' => 'Warnung',
                            'danger' => 'Fehler'
                        ]
                    ],
                    [
                        'name' => 'show_icon',
                        'label' => 'Icon anzeigen',
                        'type' => 'checkbox'
                    ]
                ]
            ]
        ];
    }

    public function render(array $data, array $children = []): string
    {
        $headline = htmlspecialchars($data['headline'] ?? '');
        $text = nl2br(htmlspecialchars($data['text'] ?? ''));
        $style = htmlspecialchars($data['style'] ?? 'info');

        return '<div class="tf-node-demo tf-demo-' . $style . '">
            <h3>' . $headline . '</h3>
            <p>' . $text . '</p>
        </div>';
    }
}
```

## Zweck

Diese Node soll Entwicklern zeigen, wie eine eigene Node aufgebaut wird.

Sie kann später erweitert werden um:

- Colorpicker
- Bildauswahl
- Repeater
- Asset-Beispiel
- allowedChildren-Beispiel
