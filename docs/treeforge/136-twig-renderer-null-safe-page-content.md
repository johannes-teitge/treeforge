# Patch 136 – Twig Renderer null-safe Page Content

## Fehler

Nach Patch 135 konnte bei Twig dieser Fehler auftreten:

```text
Argument #1 ($nodes) must be of type array, null given
```

Ursache:

```twig
{{ render_nodes(page.children)|raw }}
```

`page.children` war im Twig-Kontext nicht gesetzt und wurde deshalb als `null` an die PHP-Funktion übergeben.

## Lösung

- `page.twig` und `blank.twig` nutzen wieder das vom Renderer vorbereitete `content`.
- `page.children` wird zusätzlich in den Kontext aufgenommen.
- `render_nodes()` ist null-safe und akzeptiert fehlende oder falsche Werte ohne Fatal Error.

## Empfehlung

Core-Layouts sollten normalerweise verwenden:

```twig
{{ content|default('')|raw }}
```

Nur Spezial-Templates sollten direkt arbeiten mit:

```twig
{{ render_nodes(page.children|default([]))|raw }}
```