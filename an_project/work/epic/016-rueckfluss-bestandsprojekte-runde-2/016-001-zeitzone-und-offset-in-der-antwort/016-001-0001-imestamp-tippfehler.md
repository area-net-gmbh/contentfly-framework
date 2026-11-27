---
id: 016-001-0001
title: Der Schlüssel heisst TIMESTAMP, auch im Zweig für ungültige Datumswerte
status: review
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

- [x] `'IMESTAMP'` in `lib/contentfly/Classes/Types/DatetimeType.php` heisst `'TIMESTAMP'`.
- [x] Beide Zweige von `fromDatabase()` geben dieselben vier Schlüssel in derselben Reihenfolge
      zurück — `LOCAL_TIME`, `LOCAL`, `ISO8601`, `TIMESTAMP`. Das ist genau die Liste, die
      `ReadApiTest` heute für den gültigen Zweig festschreibt.
- [x] Ein Test deckt den Zweig für ungültige Datumswerte ab und fällt gegen den Stand von `master`
      rot. **Zu klären, bevor der Test geschrieben wird:** wie ein Wert mit Jahr `0000` unter
      MySQL 8 überhaupt in die Spalte kommt — die Vorgabe-`sql_mode` lehnt ihn ab. Lässt sich das
      nicht ohne Eingriff in die Server-Einstellung herstellen, wird `fromDatabase()` direkt
      geprüft statt über die API; dann ist das im Test zu begründen.
- [x] Festgehalten, dass hier kein Client-Vertrag bricht: der Schlüssel `IMESTAMP` war nie
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

## Ergebnis (2026-11-27)

**Eine Zeile.** `lib/contentfly/Classes/Types/DatetimeType.php:47` heisst jetzt `'TIMESTAMP'`;
die Einrückung ist an den Nachbarzweig angeglichen, damit die beiden Zweige untereinander
lesbar bleiben. Kein zweites Vorkommen von `IMESTAMP` im Baum — gegengeprüft über `lib`,
`custom`, `tools`, `tests`.

**Beide Zweige geben nun dieselben vier Schlüssel in derselben Reihenfolge zurück**
(`LOCAL_TIME`, `LOCAL`, `ISO8601`, `TIMESTAMP`) — genau die Liste, die `ReadApiTest`,
`WriteApiTest` und `TreeApiTest` für den gültigen Zweig schon festschreiben.

### Die offene Frage ist beantwortet, und einfacher als der Task annahm

Der Task hielt fest, es sei zu klären, wie ein Wert mit Jahr `0000` unter MySQL 8 in die Spalte
kommt, und nannte als Rückfall, `fromDatabase()` direkt zu prüfen. **Beides war nicht nötig: der
Zweig ist ohne Datenbank erreichbar.** PHP normalisiert `new \DateTime('0000-00-00 00:00:00')`
auf das Jahr `-0001` — gemessen unter PHP 8.3.26 —, und genau das fragt die Bedingung ab. Kein
Eingriff in die `sql_mode`, kein Server-Zustand, kein Integrationstest.

**Neuer Test `tests/Unit/Types/DatetimeTypeTest.php`**, drei Fälle:

| Test | Was er festnagelt |
|---|---|
| `testAnInvalidDateCarriesTheTimestampKeyAsWell` | der Regressionstest — die vier Schlüssel im Zweig für ungültige Datumswerte |
| `testAValidDateCarriesTheSameFourKeys` | die andere Hälfte des Vertrags: der Zweig, der immer richtig war, bleibt richtig, und beide werden gegen **eine** Liste geprüft statt gegen zwei |
| `testAFieldWithoutADateIsNullAndNotAnArray` | die Unterscheidung, die das Issue falsch hatte: ein Feld ohne Datum antwortet `null`, nicht ein Array — es hat also gar keinen Schlüssel, der falsch heissen könnte |

Der Type wird am Konstruktor vorbei gebaut (`newInstanceWithoutConstructor()`), weil
`Type::__construct()` eine ganze Application für `orm.em` verlangt und `fromDatabase()` weder
sie noch den EntityManager anfasst — dasselbe Vorgehen wie in `EmptyValueTest`, aus demselben
Grund. Eine eigene Fixture-Entity braucht der Test nicht: `Base` trägt mit `created` selbst ein
`datetime`-Feld samt Getter und Setter.

### Gegenprobe — ohne sie belegt der Test nichts

Gegen den Stand von `master` fällt **genau der Regressionstest**, mit dem Tippfehler im Diff:

```
     2 => 'ISO8601',
-    3 => 'TIMESTAMP',
+    3 => 'IMESTAMP',
```

Die anderen zwei bleiben dabei grün, und das ist richtig — sie hängen nicht am Tippfehler.
Danach wurde der Fix byte-identisch wiederhergestellt (`diff -q` gegen die abgelegte Fassung).

### Warum der Tippfehler so lange überlebt hat

**Kein Test erreichte den Zweig.** `ReadApiTest`, `WriteApiTest` und `TreeApiTest` prüfen die
vier Schlüssel je auf `created` und `modified` — beide vom Framework beim Schreiben gesetzt,
also nie ungültig. Der Zweig für ungültige Datumswerte war damit vollständig unbelegt.

### Kein Client-Vertrag bricht

Der Schlüssel `IMESTAMP` war nie dokumentiert — die API-Doc-Beispiele in
`lib/contentfly/Controller/ApiController.php` (Zeilen 366, 372, 1000) führen durchweg
`TIMESTAMP`, und `tests/Unit/ApiDocExamplesTest.php` liest genau diese Beispiele. Ein Client
konnte auf `IMESTAMP` nicht sinnvoll gebaut haben; er hätte den Schlüssel nur bei einem
ungültigen Datum überhaupt gesehen. **Kein Eintrag in `breaking-changes.md`** — festgestellt,
nicht stillschweigend ausgelassen.

### Gemessen

- **Unit-Suite `OK (506 tests, 1657 assertions)`** — vorher 503/1652, also genau die drei neuen
  Tests mit fünf Assertions
- **Volle Suite `Tests: 1217, Assertions: 7280, Skipped: 3`** — vorher 1214/7275; gefahren mit
  `tools/ci/prepare-test-environment.sh` gegen MySQL 8.0.40 aus `docker-compose.yml`
- **PHPStan `[OK] No errors`** — mit `--memory-limit=1G`; das Standardlimit von 128M lässt den
  Parallel-Worker abstürzen, das ist die Umgebung und kein Befund

Nach dem Lauf wurde die Umgebung abgebaut (`docker compose down -v`) und die von
`appcms:install` beschriebene `custom/config.php` wiederhergestellt.

### Ein Nebenbefund, der NICHT in diesen Task gehört

Der erste Testansatz scheiterte, und zwar an `Base` statt am Befund: **`Base::setCreated(null)`
ist stillschweigend wirkungslos.** Die `if/else if`-Kette hat einen Zweig für `\DateTime` und
einen für alles andere, das nicht `null` ist — und keinen für `null` selbst. Das Feld behält
damit, was der Konstruktor hineingeschrieben hat (`new \DateTime()`). Der Test hätte also „jetzt"
gemessen und es leer genannt. Er setzt das Feld deshalb per Reflection, mit der Begründung im
Code.

`setModified` trägt dieselbe Form, beide Spalten sind `nullable: true`, und es gibt eine Stelle,
an der das wirklich beisst: `DatetimeType::toDatabase()` ruft im Leer-Zweig ausdrücklich
`$object->$setter(null)`, um ein Feld zu löschen. Für `created`/`modified` tut dieser Aufruf
nichts. **Das ist ein eigener Task** (`000-000-0114`), nicht eine Zeile hier — dieser Branch
gehört `016-001-0001`.
