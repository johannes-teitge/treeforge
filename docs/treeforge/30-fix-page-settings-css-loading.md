# Fix Page Settings CSS Loading

Patch 040 behebt das Styling der Page-Settings.

## Ursache

`page-settings.css` wurde per `@import` am Ende von `admin.css` eingebunden.

CSS-Imports müssen am Anfang einer CSS-Datei stehen und können sonst vom Browser ignoriert werden.

## Fix

`AdminLayout.php` lädt nun beide Stylesheets direkt:

```html
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/assets/css/page-settings.css">
```

Der alte `@import` wurde aus `admin.css` entfernt.