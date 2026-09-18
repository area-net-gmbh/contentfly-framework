---
id: 000-000-0063
title: Feldnamen aus dem Request gehen ungeprüft in DQL
status: todo
depends_on: []
---

# Feldnamen aus dem Request gehen ungeprüft in DQL

## Context
**Gefunden bei `000-000-0062`**, beim Durchsuchen von `lib/` nach Request-Werten, die per
String-Verkettung in eine Abfrage kommen. Die **Werte** sind überall gebunden — bis auf `lang` in
`getTree2()`, das `0062` behoben hat. Offen sind die **Namen**: An vier Stellen wird ein Feldname
oder eine Sortierrichtung aus dem Request **ohne Abgleich mit dem Schema** in DQL eingesetzt.

| Stelle | Request-Parameter | eingesetzt als |
|---|---|---|
| `Api::getSingle()` | Schlüssel von `where` | `"$alias.$field = :$field"` |
| `Api::getList()` | Schlüssel **und** Wert von `order` | `addOrderBy($alias.'.'.$orderBy, $orderSort)` |
| `Api::getList()` | `groupBy` | `groupBy($alias.'.'.$groupBy)` |
| `Api::getTree()` | `properties` | `'partial '.$alias.'.{'.implode(',', $properties).'}'` |

**Das ist DQL-Injection, keine SQL-Injection** — Doctrine parst den Ausdruck, gestapelte
Anweisungen gibt es nicht. Reichen tut es trotzdem: Eine Unterabfrage im `WHERE` kann andere
Entities lesen, etwa Passwort-Hashes aus `PIM\User`, bitweise über wahr/falsch der Antwort.

**Dass es anders geht, steht im selben Code:** `getList()` prüft die `where`-Schlüssel und die
`properties` bereits gegen `$schema[...]['properties']` und wirft bei unbekannten Feldern.

## Acceptance criteria
- [ ] Alle vier Stellen gleichen Feldnamen mit `$schema[<entity>]['properties']` ab und lehnen Unbekanntes ab — dieselbe Meldung wie `getList()` heute (`contentfly_general_unknown_property`).
- [ ] Die Sortierrichtung ist auf `ASC`/`DESC` beschränkt.
- [ ] Je Stelle ein Integrationstest mit einem Feldnamen, der DQL enthält: vorher Fehler 500 oder verändertes Ergebnis, danach eine saubere Ablehnung.

## Verification
Die vier Tests, vor der Änderung rot, danach grün. Dazu die bestehende Suite unverändert grün —
sie zeigt, dass kein legitimer Aufruf ein Feld benutzt, das nicht im Schema steht.
