# Fix Page Settings Media Picker DOM Integration

Patch 069 ergänzt den Media Picker in Page Settings per DOM-Enhancement.

## Warum?

Die PHP-Struktur der Page Settings konnte durch Patch 068 nicht zuverlässig getroffen werden.

## Fix

JavaScript sucht nach:

```text
OG Image
Featured Image
```

und ergänzt automatisch:

```text
Auswählen Button
Vorschau
```

## Voraussetzung

Patch 067:

```text
window.TreeForgeMediaPicker
```