---
id: 000-000-0063
title: Feldnamen aus dem Request gehen ungeprüft in DQL
status: done
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
- [x] Alle vier Stellen gleichen Feldnamen mit `$schema[<entity>]['properties']` ab und lehnen Unbekanntes ab — dieselbe Meldung wie `getList()` heute (`contentfly_general_unknown_property`).
- [x] Die Sortierrichtung ist auf `ASC`/`DESC` beschränkt.
- [x] Je Stelle ein Integrationstest mit einem Feldnamen, der DQL enthält: vorher Fehler 500 oder verändertes Ergebnis, danach eine saubere Ablehnung.

## Verification
Die vier Tests, vor der Änderung rot, danach grün. Dazu die bestehende Suite unverändert grün —
sie zeigt, dass kein legitimer Aufruf ein Feld benutzt, das nicht im Schema steht.

## Ergebnis
**Kein Feldname aus dem Request geht mehr ungeprüft in DQL.** `Api::assertProperty()` gleicht
mit `$schema[<entity>]['properties']` ab; die Sortierrichtung ist auf `ASC`/`DESC` beschränkt
(Gross-/Kleinschreibung egal). Neue Konstanten: `contentfly_status_bad_request` (400) und
`contentfly_general_invalid_sort_direction`.

**Korrektur am Context oben:** Dort steht, `getList()` werfe bei unbekannten `where`-Feldern. Das
stimmt nicht — es **überspringt** sie stillschweigend, ebenso unbekannte `properties`. Deshalb ist
je Stelle entschieden statt pauschal:

| Stelle | unbekannter Name |
|---|---|
| `where` in `getSingle()` | **abgelehnt, 400** — ohne den Filter käme ein **anderer** Datensatz zurück |
| `order` in `getList()` | **abgelehnt, 400**; Richtung nur `ASC`/`DESC` |
| `groupBy` in `getList()` | **abgelehnt, 400** |
| `properties` in `getTree()` | **verworfen** — genau wie `getList()` mit seinen `properties`; Tree strenger als List wäre eine neue Ungleichheit |

Die letzte Zeile **weicht vom ersten Kriterium ab** („lehnen Unbekanntes ab"), mit diesem Grund.

**Status 400 statt 500:** Ein unbekannter Feldname ist ein Fehler des Clients. Die Ausnahme hatte
bisher den Standard 500.

**`tests/Integration/Api/FieldNameApiTest.php`**, je Stelle ein Name mit DQL und ein legitimer
Aufruf (Sortierung auch nach `id` und `created`). **Vor dem Fix alle fünf Ablehnungsfälle rot mit
500** — der Name kam bis in den DQL-Parser. Danach grün; die Suite 680 Tests, PHPStan ohne
Fehler.
