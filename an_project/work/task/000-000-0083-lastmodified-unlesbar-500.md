---
id: 000-000-0083
title: Ein nicht lesbares lastModified in /api/list mit 400 statt 500 beantworten
status: done
depends_on: []
---

# Ein nicht lesbares lastModified in /api/list mit 400 statt 500 beantworten

## Context
**Gefunden in `000-000-0075` (2026-09-22).** `getList()` versucht `lastModified` als Datum zu lesen,
schluckt den Fehler und gibt die rohe Zeichenkette an die Abfrage weiter. MySQL lehnt sie ab
(`Incorrect DATETIME value`), der Aufrufer bekommt 500 für einen falschen Parameter. `/api/all`
liest den Wert im Controller und verwirft ihn stillschweigend; `/api/count` gibt ihn ungeprüft an
SQL — dort auf dasselbe Verhalten prüfen.

Festgehalten in `ReadPathApiTest::testAnUnreadableLastModifiedEndsInAServerError()`.

## Acceptance criteria
- [x] Ein nicht lesbares `lastModified` antwortet in `/api/list` mit 400 und einem Meldungsschlüssel, der das Feld nennt.
- [x] `/api/all` und `/api/count` verhalten sich gleich, oder die Abweichung ist begründet.
- [x] Der Test ist umgedreht; ein Test je betroffenem Endpunkt.

## Verification
`ReadPathApiTest` grün; die Suite bleibt grün.

## Ergebnis (2026-09-22)
**Ein nicht lesbares `lastModified` antwortet in allen vier Endpunkten mit 400
`contentfly_general_invalid_date`, `context.value` = `lastModified`.**

- Neu `Api::readDate()` (ein Zeitpunkt oder `null`) und `Api::assertDates()` (einer oder einer je
  Entity). `getList()` statt des geschluckten `try`, `getCount()` und `getDeleted()` prüfen vorab,
  `ApiController::allAction()` liest den Wert über `readDate()`.
- `/api/all` verwarf den Wert bisher stillschweigend und lieferte alles; `/api/count` und
  `/api/deleted` antworteten wie `/api/list` mit 500. Jetzt alle gleich.
- Eine **Zahl** gilt als nicht lesbar — bei `/api/count` war sie ohnehin wirkungslos. Ein leerer
  Wert heisst weiterhin: kein Zeitpunkt.
- Registereintrag in `breaking-changes.md`.

### Belegt
`ReadPathApiTest::testAnUnreadableLastModifiedIsTheCallersMistake` mit sieben Fällen (je Endpunkt,
je Entity, eine Zahl) und `…testAnEmptyLastModifiedMeansNone`. **Gegenprobe:** ohne die Änderung 7
von 8 rot. Suite 790 Tests grün, PHPStan ohne Fehler.

