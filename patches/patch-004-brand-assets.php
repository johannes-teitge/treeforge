<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 004
 * Brand assets + design system documentation.
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 004 Brand Assets gestartet');

    $write($root . '/public/assets/brand/treeforge-logo.svg', <<<'SVG'
<?xml version="1.0" encoding="UTF-8" standalone="no"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg width="100%" height="100%" viewBox="0 0 1396 204" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;"><path d="M114.587,25.419c-0,-13.946 12.929,-25.419 28.646,-25.419l19.098,0c15.718,0 28.647,11.473 28.647,25.419l-0,16.946c-0,13.947 -12.929,25.419 -28.647,25.419l0,16.946l105.038,0c5.233,0 9.549,3.83 9.549,8.474l-0,16.946c-0,4.643 -4.316,8.473 -9.549,8.473c-5.233,-0 -9.549,-3.83 -9.549,-8.473l-0,-8.473l-95.489,-0l0,8.473c0,4.643 -4.316,8.473 -9.549,8.473c-5.233,-0 -9.549,-3.83 -9.549,-8.473l0,-8.473l-95.489,-0l0,8.473c0,4.643 -4.316,8.473 -9.548,8.473c-5.233,-0 -9.549,-3.83 -9.549,-8.473l-0,-16.946c-0,-4.644 4.316,-8.474 9.549,-8.474l105.037,0l0,-16.946c-15.717,0 -28.646,-11.472 -28.646,-25.419l-0,-16.946Zm-114.587,135.569c-0,-13.947 12.929,-25.419 28.647,-25.419l19.097,-0c15.718,-0 28.647,11.472 28.647,25.419l0,16.946c0,13.947 -12.929,25.419 -28.647,25.419l-19.097,0c-15.718,0 -28.647,-11.472 -28.647,-25.419l-0,-16.946Zm114.587,-0c-0,-13.947 12.929,-25.419 28.646,-25.419l19.098,-0c15.718,-0 28.647,11.472 28.647,25.419l-0,16.946c-0,13.947 -12.929,25.419 -28.647,25.419l-19.098,0c-15.717,0 -28.646,-11.472 -28.646,-25.419l-0,-16.946Zm114.586,-0c0,-13.947 12.93,-25.419 28.647,-25.419l19.098,-0c15.717,-0 28.646,11.472 28.646,25.419l0,16.946c0,13.947 -12.929,25.419 -28.646,25.419l-19.098,0c-15.717,0 -28.647,-11.472 -28.647,-25.419l0,-16.946Z" style="fill:#1e3d1c;"/><g transform="matrix(3.125,0,0,3.125,350.457,180.126)"><text x="0px" y="0px" style="font-family:'BarlowCondensed-SemiBold', 'Barlow Condensed';font-weight:600;font-stretch:condensed;font-size:74.667px;fill:#1e3d1c;">T<tspan x="36.736px 74.144px 109.611px " y="0px 0px 0px ">REE</tspan></text><text x="145.077px" y="0px" style="font-family:'BarlowCondensed-SemiBold', 'Barlow Condensed';font-weight:600;font-stretch:condensed;font-size:74.667px;fill:#d88a22;">F<tspan x="179.051px 216.907px 254.315px 291.797px " y="0px 0px 0px 0px ">ORGE</tspan></text></g></svg>
SVG);

    $write($root . '/public/assets/brand/treeforge-icon.svg', <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 306 204" role="img" aria-label="TreeForge Icon">
  <path d="M114.587,25.419c-0,-13.946 12.929,-25.419 28.646,-25.419l19.098,0c15.718,0 28.647,11.473 28.647,25.419l-0,16.946c-0,13.947 -12.929,25.419 -28.647,25.419l0,16.946l105.038,0c5.233,0 9.549,3.83 9.549,8.474l-0,16.946c-0,4.643 -4.316,8.473 -9.549,8.473c-5.233,-0 -9.549,-3.83 -9.549,-8.473l-0,-8.473l-95.489,-0l0,8.473c0,4.643 -4.316,8.473 -9.549,8.473c-5.233,-0 -9.549,-3.83 -9.549,-8.473l0,-8.473l-95.489,-0l0,8.473c0,4.643 -4.316,8.473 -9.548,8.473c-5.233,-0 -9.549,-3.83 -9.549,-8.473l-0,-16.946c-0,-4.644 4.316,-8.474 9.549,-8.474l105.037,0l0,-16.946c-15.717,0 -28.646,-11.472 -28.646,-25.419l-0,-16.946Zm-114.587,135.569c-0,-13.947 12.929,-25.419 28.647,-25.419l19.097,-0c15.718,-0 28.647,11.472 28.647,25.419l0,16.946c0,13.947 -12.929,25.419 -28.647,25.419l-19.097,0c-15.718,0 -28.647,-11.472 -28.647,-25.419l-0,-16.946Zm114.587,-0c-0,-13.947 12.929,-25.419 28.646,-25.419l19.098,-0c15.718,-0 28.647,11.472 28.647,25.419l-0,16.946c-0,13.947 -12.929,25.419 -28.647,25.419l-19.098,0c-15.717,0 -28.646,-11.472 -28.646,-25.419l-0,-16.946Zm114.586,-0c0,-13.947 12.93,-25.419 28.647,-25.419l19.098,-0c15.717,-0 28.646,11.472 28.646,25.419l0,16.946c0,13.947 -12.929,25.419 -28.646,25.419l-19.098,0c-15.717,0 -28.647,-11.472 -28.647,-25.419l0,-16.946Z" fill="#1E3D1C"/>
</svg>
SVG);

    $write($root . '/docs/design-system.md', <<<'MD'
# TreeForge Design System

## Markenidee

TreeForge steht für strukturierte Inhalte als Baum.

**Leitsatz:**  
Structure first. Content grows.

## Logo

### Hauptlogo

Pfad:

```text
public/assets/brand/treeforge-logo.svg
```

### Icon

Pfad:

```text
public/assets/brand/treeforge-icon.svg
```

Das Icon basiert auf einer Node-/Baumstruktur und steht für:

- Content Tree
- Nodes
- Hierarchie
- Struktur
- objektorientiertes CMS

## Farben

```css
:root {
  --tf-green: #1E3D1C;
  --tf-gold:  #D88A22;
  --tf-dark:  #121A17;
  --tf-light: #F5F3EA;
  --tf-cream: #FFFAF0;
}
```

### Nutzung

- `--tf-green`: TREE, Icons, Primärfarbe
- `--tf-gold`: FORGE, Highlights, Akzente
- `--tf-dark`: Text, dunkle Flächen
- `--tf-light`: Seitenhintergrund
- `--tf-cream`: Cards, Panels, helle Boxen

## Typografie

### Logo

```css
font-family: "Barlow Condensed", "Arial Narrow", sans-serif;
font-weight: 600;
```

### UI

```css
font-family: "Inter", system-ui, sans-serif;
```

### Code

```css
font-family: "JetBrains Mono", Consolas, monospace;
```

## Schreibweise

```text
TREEFORGE
```

- TREE = Grün
- FORGE = Gold

Langfristig kann TreeForge ohne Zusatz „CMS“ als eigenständige Marke verwendet werden.

## Design-Prinzipien

- klar
- technisch
- reduziert
- strukturiert
- entwicklerfreundlich
- nicht verspielt
- nicht wie ein klassischer Pagebuilder

## Komponenten-Stil

- Bootstrap 5 als Basis
- abgerundete Cards
- ruhige Natur-/Tech-Farbwelt
- klare Icons
- viel Weißraum
- Strukturbaum als zentrales UI-Element

MD);

    $write($root . '/public/assets/css/brand.css', <<<'CSS'
:root {
  --tf-green: #1E3D1C;
  --tf-gold:  #D88A22;
  --tf-dark:  #121A17;
  --tf-light: #F5F3EA;
  --tf-cream: #FFFAF0;

  --tf-font-logo: "Barlow Condensed", "Arial Narrow", sans-serif;
  --tf-font-ui: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  --tf-font-code: "JetBrains Mono", Consolas, monospace;
}

.tf-logo-text {
  font-family: var(--tf-font-logo);
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.tf-logo-tree {
  color: var(--tf-green);
}

.tf-logo-forge {
  color: var(--tf-gold);
}
CSS);

    $log('Patch 004 Brand Assets fertig');
};
