---
id: 000-000-0032
title: SyncApiTest stellt die Sekunde nicht her, die er im Namen führt
status: done
depends_on: []
---

# `SyncApiTest` stellt die Sekunde nicht her, die er im Namen führt

## Context
**Gefunden bei `007-004-0003`**, als ein voller Lauf einen Fehlschlag meldete, den meine
Änderungen nicht verursacht haben konnten — sie betrafen zwei Dokumente und eine neue Testdatei.

`tests/Integration/Api/SyncApiTest::testZweiLoeschungenInDerselbenSekundeKommenBeide` schreibt
zwei Zeilen in `pim_log` und prüft, dass ein Sync-Client **beide** bekommt, wenn er sich den
Zeitstempel der zweiten merkt. Das ist der Nachweis zu `000-000-0013 C`: `pim_log.created` hat
Sekundenauflösung, und mit dem alten `created > ?` verlor ein Client jede Löschung aus der
Sekunde, deren Zeitstempel er sich gemerkt hatte.

**Der Test heisst „in derselben Sekunde" — und stellt das nicht sicher.** Beide Zeilen bekommen
`NOW()`, in zwei getrennten `INSERT`s:

```sql
INSERT INTO pim_log (…, created, modified, …) VALUES (…, NOW(), NOW(), …)
```

Fällt eine Sekundengrenze zwischen die beiden, trägt die erste Zeile die Sekunde davor. Die
Abfrage filtert dann ab der zweiten, und die erste fällt aus dem Ergebnis — der Test wird rot,
obwohl am Framework nichts falsch ist.

## Gemessen, nicht vermutet

Der erste Versuch, es nachzustellen, fand nichts: **2000 Paare `SELECT NOW()` lagen alle in
derselben Sekunde.** Zwei nackte Abfragen liegen unter einer Millisekunde auseinander.

Mit dem **echten** Ablauf — zwei vorbereiteten `INSERT`s mit demselben Aufwand drumherum:

```
2 von 1500 Paaren fielen auseinander (0,13 %)
```

Das passt zu dem, was zu sehen war: ein Fehlschlag in vielen Läufen. Der Lauf unmittelbar danach
war grün.

## Warum das zählt

**Ein Test, der in einem von etwa 750 Läufen ohne Grund rot wird, ist schlimmer als keiner.**
Er kostet jedes Mal die Frage „ist das echt?", und beim dritten Mal beantwortet sie jemand mit
„nein" — auch dann, wenn sie „ja" wäre.

**Die Zusicherung selbst ist richtig und soll bleiben.** Was fehlt, ist ihre Voraussetzung: Der
Test muss die gemeinsame Sekunde **herstellen**, statt auf sie zu hoffen.

## Acceptance criteria
- [x] Beide Zeilen tragen nachweislich denselben Zeitstempel — hergestellt, nicht erhofft.
- [x] Die Zusicherung des Tests ist unverändert: Ein Client, der sich den Zeitstempel der zweiten Löschung merkt, bekommt beide.
- [x] Der Test prüft weiterhin das Verhalten der API und nicht das seiner eigenen Vorbereitung.
- [x] Andere Tests, die `NOW()` zweimal aufrufen und eine gemeinsame Sekunde annehmen, sind gesucht und entweder mitgezogen oder als unkritisch benannt.
- [x] Die volle Suite bleibt grün.

## Verification
Den geänderten Test 1500-mal gegen dieselbe Datenbank fahren — kein Fehlschlag. Zum Vergleich
die Messung von oben: Mit `NOW()` fielen 2 von 1500 Paaren auseinander.

## Ergebnis

**Die gemeinsame Sekunde wird hergestellt statt erhofft.** Der Test holt einmal `SELECT NOW()`
aus der Datenbank und bindet diesen einen Wert an beide Zeilen. Die Datenbankuhr bleibt die
Quelle, wie vorher; es fällt nur die Lücke zwischen zwei Aufrufen weg. `logRow()` nimmt dafür
einen optionalen Zeitstempel, ohne ihn entscheidet weiter `NOW()` (`COALESCE` im selben
Statement). Die beiden anderen Aufrufer sind unverändert.

**Die Zusicherung ist dieselbe:** Ein Client, der sich den Zeitstempel der zweiten Löschung merkt,
bekommt über `/api/deleted` beide. Geprüft wird weiterhin nur die Antwort der API. Eine
zusätzliche Assertion auf die Vorbereitung gibt es bewusst nicht: Beide Zeilen tragen den
Zeitstempel durch Konstruktion, weil es ein und derselbe gebundene Wert ist.

**Andere Tests mit `NOW()` — gesucht, keiner mitzuziehen.** 19 Fundstellen unter `tests/`:

- Alle `INSERT … NOW(), NOW()` rufen die Funktion **zweimal im selben Statement** auf. MySQL
  wertet `NOW()` einmal pro Statement aus; `created` und `modified` sind dort immer gleich.
  Unkritisch.
- `UpdateReplaceApiTest::testBothUpdateModified` setzt `modified` auf einen festen Wert aus dem
  Jahr 2000 und vergleicht dagegen. Unkritisch.
- `LogSideEffectApiTest::testLogRowTimestampHasOnlySecondResolution` prüft ausdrücklich die
  Auflösung, nicht die Gleichheit zweier Aufrufe — derselbe Fehler war dort schon früher
  aufgefallen und behoben.
- `AuthApiTest` nutzt `NOW()` nur als Grenze beim Aufräumen. Unkritisch.

**Verifiziert:** Der geänderte Test 1500-mal hintereinander gegen dieselbe Datenbank —
**0 Fehlschläge** (Vergleich: mit zwei `NOW()` fielen 2 von 1500 Paaren auseinander). Volle
Suite `Tests: 528, Assertions: 1697, Skipped: 3`, Deprecation-Gate 0 Zeilen.
