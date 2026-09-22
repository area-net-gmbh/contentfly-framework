---
id: 000-000-0083
title: Ein nicht lesbares lastModified in /api/list mit 400 statt 500 beantworten
status: todo
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
- [ ] Ein nicht lesbares `lastModified` antwortet in `/api/list` mit 400 und einem Meldungsschlüssel, der das Feld nennt.
- [ ] `/api/all` und `/api/count` verhalten sich gleich, oder die Abweichung ist begründet.
- [ ] Der Test ist umgedreht; ein Test je betroffenem Endpunkt.

## Verification
`ReadPathApiTest` grün; die Suite bleibt grün.
