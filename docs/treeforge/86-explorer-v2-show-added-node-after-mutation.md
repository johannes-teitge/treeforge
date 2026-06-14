# Explorer V2 Show Added Node After Mutation

Patch 097 sorgt dafür, dass frisch eingefügte Nodes sichtbar werden.

## Änderungen

- nach Add immer auf `workspace=draft`
- collapsed Node Tree State wird gelöscht
- Cache-Buster `_` wird an URL gehängt
- neue Node-ID wird in `sessionStorage` gemerkt
- nach Reload wird die neue Node markiert und ins Sichtfeld gescrollt