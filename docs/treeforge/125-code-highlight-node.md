# Patch 125 – CodeBlockNode / Code-Highlighter

Dieser Patch ergänzt einen eigenen Inhalts-Node für sichtbare Code-Beispiele.

## Warum nicht HTML/CSS-Node?

HTML- und CSS-Nodes sind technische Spezial-Nodes. Der CodeBlockNode ist dagegen Content: Er zeigt Code an, führt ihn aber nicht aus.

## Node-Typen

- `CodeBlockNode` im Explorer V2
- `codeblock` im Legacy NodeCreator

## Eigenschaften

```json
{
  "type": "CodeBlockNode",
  "properties": {
    "content": {
      "language": "php",
      "code": "<?php\necho 'Hallo TreeForge';\n",
      "caption": "",
      "show_line_numbers": "1",
      "wrap": "0"
    }
  }
}
```

## Rendering später

Der Node soll später als echtes, sicheres Code-HTML gerendert werden:

```html
<figure class="tf-node tf-node-codeblock">
  <pre><code class="language-php">...</code></pre>
</figure>
```

Wichtig: Der Code wird immer escaped und niemals ausgeführt.