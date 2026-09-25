---
id: 000-000-0076
title: /api/query — die Array-Syntax testen oder streichen
status: review
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
- [x] Entschieden: Die Array-Syntax bleibt (dann Tests: mehrere Joins, mehrere Bedingungen, Rechte-Verengung) oder entfällt (dann Registereintrag).
- [x] Die DOC-Beispiele in `ApiController::queryAction` passen zur Entscheidung.

## Verification
`QueryApiTest` erweitert; Coverage von `getQuery()` neu gemessen.

## Ergebnis (2026-09-25)
**Die Array-Syntax bleibt, entschieden am 2026-09-25.** Sie ist der einzige Weg, einen Join
auszudrücken: Die Objekt-Syntax übergibt zwei Argumente, ein Join braucht vier. Und
`"select": ["title", …]` nimmt ebenfalls diesen Zweig — das steht im eigenen Beispiel der DOC.
Streichen hätte jedem Client Joins und mehrspaltige Selects genommen.

### Tests — `QueryApiTest`, von 9 auf 24
| Fall | Test |
|---|---|
| `select` als Liste | alle genannten Spalten, genau diese |
| mehrere Joins | zwei in einer Anfrage, einer über den Entity-Namen, einer über den Tabellennamen |
| ein Join als flache Liste | `["t", "PIM\\Tag", "j", "…"]` |
| Join mit weniger als vier Teilen | `contentfly_general_invalid_params` |
| mehrere Bedingungen | `"where": ["a", "b"]` — beide greifen |
| mehrere Werte zu einer Bedingung | `{"a = ? OR b = ?": ["x", "y"]}` |
| **Rechte auf die gejointe Entity** | `OWN`, `GROUP`, `ALL` je gegen eigene, über `users` freigegebene, gruppen-freigegebene und fremde Tags; ohne Leserecht 403 |
| **Rechte auf die `from`-Entity** | `OWN` und `GROUP`, je als Name und mit Alias — die beiden Formen laufen durch getrennte Zweige; ohne Leserecht 403, über Entity- wie Tabellennamen |

**Gegenprobe:** Mit ausgeschalteter `OWN`-Verengung im Join-Zweig wird der Join-Test rot. Er misst
also die Verengung, nicht nur den Status.

### DOC-Beispiele
- Neues Beispiel *Query with joins and several conditions*: zwei Joins als Liste von Listen,
  `select` und `where` als Liste.
- Die Beschreibung erklärt die Array-Syntax und dass jede Entity in `from` und in einem Join nach
  dem Leserecht verengt wird.
- **`having` korrigiert:** `{"field": "value"}` wird zu `having("field", "value")`, also
  `(field) AND (value)` — `value` als Spaltenname. Jetzt `{"users > ?": 1}`, die Form mit
  gebundenem Wert.

### Coverage von `getQuery()`, neu gemessen
Voller Lauf mit PCOV, 865 Tests grün.

| | vorher | nachher |
|---|---|---|
| `getQuery` | 34 / 132 | **117 / 132** |
| `isIndexedArray` | 0 / 2 | **2 / 2** |
| `Api.php` gesamt | 964 / 1.238 (77,9 %) | **1.050 / 1.238 (84,8 %)** |

**Offen bleiben 15 Zeilen:** die unerreichbaren „ohne Gruppe“-Zweige (siehe `0074`: ohne Gruppe
gibt es die Stufe `GROUP` nicht), `break`-Zeilen, die PCOV nicht zählt, die Suche über den
Tabellennamen bei `from` mit Alias, `from` mit Alias ohne Leserecht, und die Ablehnung von
`delete`/`insert`/`update` als Methode.
