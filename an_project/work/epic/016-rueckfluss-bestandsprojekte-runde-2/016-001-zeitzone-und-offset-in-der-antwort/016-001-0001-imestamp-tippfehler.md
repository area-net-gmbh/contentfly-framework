---
id: 016-001-0001
title: Der Schlüssel heisst TIMESTAMP, auch im Zweig für ungültige Datumswerte
status: todo
depends_on: []
---

# Der Schlüssel heisst TIMESTAMP, auch im Zweig für ungültige Datumswerte

## Context

Aus `#105`, Punkt 1 — die kleinste der vier Zeilen der Story und eine reine Fehlerkorrektur:
`DatetimeType::fromDatabase()` gibt in einem Zweig `'IMESTAMP'` statt `'TIMESTAMP'` zurück. Ein
Client, der den Schlüssel sucht, findet ihn dort nicht. Der Task hängt an keiner Entscheidung der
Story und soll nicht auf die UTC-Frage warten.

**Der Zweig ist nicht der, den das Issue nennt — das ist beim Umsetzen wichtig.** Das Issue spricht
vom „Null-Zweig". Ein NULL in der Spalte erreicht diese Stelle aber gar nicht: `fromDatabase()`
steigt vier Zeilen vorher aus, wenn der Getter kein `\DateTime` liefert, und gibt dann blank `null`
zurück. Der Zweig mit dem Tippfehler greift bei einem **ungültigen Datum** — die Bedingung prüft
`format('Y')` gegen `'-0001'` und `'0000'`, also einen Wert wie `0000-00-00 00:00:00`. Wer den
Befund am NULL-Fall zu reproduzieren versucht, sucht an der falschen Stelle.

Dass der Tippfehler so lange überlebt hat, hat denselben Grund: **kein Test erreicht diesen
Zweig.** `ReadApiTest`, `WriteApiTest` und `TreeApiTest` prüfen die vier Schlüssel je auf `created`
und `modified` — beide vom Framework beim Schreiben gesetzt, also nie ungültig.

## Acceptance criteria

- [ ] `'IMESTAMP'` in `lib/contentfly/Classes/Types/DatetimeType.php` heisst `'TIMESTAMP'`.
- [ ] Beide Zweige von `fromDatabase()` geben dieselben vier Schlüssel in derselben Reihenfolge
      zurück — `LOCAL_TIME`, `LOCAL`, `ISO8601`, `TIMESTAMP`. Das ist genau die Liste, die
      `ReadApiTest` heute für den gültigen Zweig festschreibt.
- [ ] Ein Test deckt den Zweig für ungültige Datumswerte ab und fällt gegen den Stand von `master`
      rot. **Zu klären, bevor der Test geschrieben wird:** wie ein Wert mit Jahr `0000` unter
      MySQL 8 überhaupt in die Spalte kommt — die Vorgabe-`sql_mode` lehnt ihn ab. Lässt sich das
      nicht ohne Eingriff in die Server-Einstellung herstellen, wird `fromDatabase()` direkt
      geprüft statt über die API; dann ist das im Test zu begründen.
- [ ] Festgehalten, dass hier kein Client-Vertrag bricht: der Schlüssel `IMESTAMP` war nie
      dokumentiert und kein Client kann sinnvoll darauf gebaut haben. Kein Eintrag in
      `breaking-changes.md` nötig — diese Feststellung gehört aber in den Abschluss, nicht ins
      Stillschweigen.

## Verification

```sh
./vendor/bin/phpunit --testsuite unit            # muss immer grün sein
./vendor/bin/phpunit                             # beide Suiten
```

Dazu die Gegenprobe: der neue Test gegen den Stand von `master` — rot, mit dem fehlenden Schlüssel
als Meldung. Ohne diese Gegenprobe belegt der Test nichts.
