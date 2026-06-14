# Patch 140 – Fix Preview Bar Array Rendering

## Problem

Nach Patch 139 erschien im Frontend oben:

```text
Array
```

und PHP meldete:

```text
Warning: Array to string conversion in vendor/twig/twig/src/Template.php
```

Ursache: `preview_bar` ist ein Array. `base.twig` versuchte aber, es direkt mit `|raw` auszugeben.

## Lösung

`base.twig` rendert die Preview-Bar wieder strukturiert:

```twig
{% if preview_bar.enabled|default(false) %}
  <div class="tf-preview-bar">...</div>
{% endif %}
```

Dadurch wird kein Array mehr als String ausgegeben.