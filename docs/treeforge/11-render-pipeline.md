# Render-Pipeline

Die Render-Pipeline beschreibt, wie TreeForge aus einem gespeicherten Node-Baum fertiges HTML erzeugt.

## Ablauf

```text
RootNode bestimmen
    ↓
Node-Tree laden
    ↓
Nodes nach Position sortieren
    ↓
Node-Typen über NodeRegistry auflösen
    ↓
Node-Hierarchie validieren
    ↓
Active prüfen
    ↓
ValidFrom prüfen
    ↓
ValidUntil prüfen
    ↓
Berechtigungen prüfen
    ↓
Assets sammeln
    ↓
Children rekursiv rendern
    ↓
Node rendern
    ↓
HTML zusammenbauen
    ↓
CSS/JS zentral ausgeben
```

## RootNode

Jeder Content-Datensatz besitzt eine RootNode.

Die RootNode kann z. B. sein:

- PageRootNode
- BlogRootNode
- NewsRootNode
- ProductRootNode

Die RootNode selbst wird normalerweise nicht als sichtbarer Block gerendert, sondern dient als Einstiegspunkt.

## Validierung

Vor dem Rendern prüft TreeForge:

- existiert der Node-Typ?
- darf der Node unter diesem Parent liegen?
- darf der Parent diesen Child-Typ enthalten?
- ist die Node aktiv?
- liegt die aktuelle Zeit innerhalb von valid_from und valid_until?
- besitzt der Benutzer die notwendigen Rechte?

## Rekursives Rendering

Children werden zuerst gerendert.

Danach erhält die Parent-Node das fertige Child-HTML.

Beispiel:

```php
$html = $node->render($data, $childrenHtml);
```

## Asset-Sammlung

Während des Renderns sammelt der AssetCollector alle Assets der verwendeten Nodes.

Dadurch werden nur CSS- und JS-Dateien geladen, die im aktuellen Baum wirklich benötigt werden.

## Fehlerverhalten

Wenn ein Node-Typ nicht gefunden wird, sollte TreeForge nicht die gesamte Seite zerstören.

Empfohlen:

- im Frontend: Node überspringen oder Fallback-Kommentar ausgeben
- im Editor: Warnbox anzeigen
- im Log: Fehler protokollieren

Beispiel Frontend-Kommentar:

```html
<!-- TreeForge: unknown node type "demo_box" -->
```
