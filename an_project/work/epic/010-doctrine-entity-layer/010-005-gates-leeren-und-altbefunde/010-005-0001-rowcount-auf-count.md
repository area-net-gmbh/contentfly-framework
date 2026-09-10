---
id: 010-005-0001
title: Die rowCount-Stellen auf COUNT(*) bringen
status: todo
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
- [ ] Die drei Stellen benutzen `SELECT COUNT(*)` und lesen den Wert als Zahl, nicht über `rowCount()`.
- [ ] Die gelieferten Zahlen sind unverändert — belegt über `SyncApiTest`, nicht nur über die Suite als Ganzes.
- [ ] `rowCount()` kommt in `Classes/Api.php` nicht mehr für eine Leseabfrage vor.
- [ ] Die Suite bleibt grün, ohne eine geänderte Zusicherung.

## Verification
`./vendor/bin/phpunit --filter SyncApiTest`, dann die volle Suite. Zusätzlich ein Vergleich
der gelieferten `dataCount`-Werte vor und nach der Änderung, gegen dieselbe Datenbank — eine
Zahl, die sich verschiebt, wäre in der Suite womöglich unsichtbar.
