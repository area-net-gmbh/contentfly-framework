---
id: 010-005-0001
title: Die rowCount-Stellen auf COUNT(*) bringen
status: done
depends_on: []
---

# Die rowCount-Stellen auf COUNT(*) bringen

## Context
Drei Stellen in `Classes/Api.php` (Zeilen 864, 878, 900) zählen so:

```php
$anzahl = $this->app['database']->executeQuery("SELECT 1 FROM …", $params)->rowCount();
```

**Sie zählen richtig und arbeiten falsch.** `SELECT 1` liefert eine Zeile je Treffer; alle davon
gehen über die Verbindung und in den Speicher, nur damit `rowCount()` sie abzählt. Bei einer
Tabelle mit vielen Zeilen ist das der Unterschied zwischen einer Zahl und einem Datenübertrag.

Befund aus `009-005-0002`, dort bewusst nicht angefasst: Es war keine Bruchstelle, und die
Story sollte den Kernel entblockieren.

**`rowCount()` ist ausserdem für SELECT nicht zugesichert.** DBAL sagt es deutlich: Der
Rückgabewert bei einer Leseabfrage hängt vom Treiber ab. Dass es unter MySQL funktioniert, ist
kein Vertrag.

Die drei Stellen gehören zu `/api/sync` und zählen, wie viele Datensätze sich seit einem
Zeitpunkt geändert haben — `SyncApiTest` deckt das ab.

## Acceptance criteria
- [x] Die drei Stellen benutzen `SELECT COUNT(*)` und lesen den Wert als Zahl, nicht über `rowCount()`.
- [x] Die gelieferten Zahlen sind unverändert — belegt über `SyncApiTest`, nicht nur über die Suite als Ganzes.
- [x] `rowCount()` kommt in `Classes/Api.php` nicht mehr für eine Leseabfrage vor.
- [x] Die Suite bleibt grün, ohne eine geänderte Zusicherung.

## Verification
`./vendor/bin/phpunit --filter SyncApiTest`, dann die volle Suite. Zusätzlich ein Vergleich
der gelieferten `dataCount`-Werte vor und nach der Änderung, gegen dieselbe Datenbank — eine
Zahl, die sich verschiebt, wäre in der Suite womöglich unsichtbar.

## Ergebnis

**Die drei Stellen zählen jetzt mit `SELECT COUNT(*)` und lesen den Wert über `fetchOne()`.**
`rowCount()` kommt im ganzen Baum nicht mehr für eine Leseabfrage vor.

### Die Zahlen sind unverändert, und zwar gemessen

Der Vergleich der gelieferten `dataCount`- und `details`-Werte über fünf Entities, gegen
dieselbe Datenbank, vor und nach der Änderung:

| Entity | `dataCount` |
|---|---|
| `Core\Example` | 5 |
| `PIM\User` | 1 |
| `PIM\Group`, `PIM\File`, `PIM\Tag` | 0 |

**Identisch, Zeichen für Zeichen.** Das war nötig, weil eine verschobene Zahl in der Suite
womöglich unsichtbar geblieben wäre: `SyncApiTest` prüft `assertGreaterThanOrEqual(1, …)` und
`assertIsInt(…)` — beides hielte auch einer falschen Zahl stand.

**Beim Messen selbst danebengegriffen:** Meine erste Probe rief `/api/sync` — den Endpunkt gibt
es nicht, die Antwort war ein 405. Es ist `/api/count`. Und die zweite zählte `PIM\Tag`, wovon
es keine Zeile gab; eine Null gegen eine Null zu vergleichen belegt nichts. Erst der dritte
Anlauf mass etwas.

### Warum es überhaupt zählt

`SELECT 1` liefert eine Zeile je Treffer. Alle gehen über die Verbindung und in den Speicher,
nur damit `rowCount()` sie abzählt. Bei einer Tabelle mit vielen Zeilen ist das der Unterschied
zwischen einer Zahl und einem Datenübertrag.

Dazu ist `rowCount()` für eine **Lese**abfrage nicht zugesichert — DBAL sagt, der Rückgabewert
hänge dann vom Treiber ab. Dass es unter MySQL ging, war kein Vertrag.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `dataCount` und `details`, vorher gegen nachher | **identisch** über fünf Entities |
| `SyncApiTest` | `OK (13 tests, 36 assertions)` |
| Volle Suite | `OK (282 tests, 692 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| `rowCount()` für eine Leseabfrage im Baum | 0 Treffer |
