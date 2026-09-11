---
id: 000-000-0032
title: SyncApiTest stellt die Sekunde nicht her, die er im Namen führt
status: todo
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
- [ ] Beide Zeilen tragen nachweislich denselben Zeitstempel — hergestellt, nicht erhofft.
- [ ] Die Zusicherung des Tests ist unverändert: Ein Client, der sich den Zeitstempel der zweiten Löschung merkt, bekommt beide.
- [ ] Der Test prüft weiterhin das Verhalten der API und nicht das seiner eigenen Vorbereitung.
- [ ] Andere Tests, die `NOW()` zweimal aufrufen und eine gemeinsame Sekunde annehmen, sind gesucht und entweder mitgezogen oder als unkritisch benannt.
- [ ] Die volle Suite bleibt grün.

## Verification
Den geänderten Test 1500-mal gegen dieselbe Datenbank fahren — kein Fehlschlag. Zum Vergleich
die Messung von oben: Mit `NOW()` fielen 2 von 1500 Paaren auseinander.
