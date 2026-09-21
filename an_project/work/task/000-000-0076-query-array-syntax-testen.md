---
id: 000-000-0076
title: /api/query — die Array-Syntax testen oder streichen
status: todo
depends_on: [000-000-0072]
---

# /api/query — die Array-Syntax testen oder streichen

## Context
**Aus `000-000-0069`.** Der grösste zusammenhängende offene Block in `Api.php`: **84 Zeilen** in
`getQuery()` (dazu `isIndexedArray()`), der Zweig für Parameter als indiziertes Array — mehrere
`join`/`where`-Aufrufe je Methode. `QueryApiTest` benutzt nur die Objekt-Syntax.

`/api/query` baut Abfragen aus dem Request; der Endpunkt ist per `apiQueryEnabled` je Gruppe
schaltbar und war Gegenstand von `000-000-0062`/`0063`. Ungetesteter Code an dieser Stelle ist
Angriffsfläche.

## Acceptance criteria
- [ ] Entschieden: Die Array-Syntax bleibt (dann Tests: mehrere Joins, mehrere Bedingungen, Rechte-Verengung) oder entfällt (dann Registereintrag).
- [ ] Die DOC-Beispiele in `ApiController::queryAction` passen zur Entscheidung.

## Verification
`QueryApiTest` erweitert; Coverage von `getQuery()` neu gemessen.
