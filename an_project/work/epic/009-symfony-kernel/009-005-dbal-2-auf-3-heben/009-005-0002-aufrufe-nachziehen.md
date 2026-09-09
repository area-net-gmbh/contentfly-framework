---
id: 009-005-0002
title: Die entfallenen DBAL-Aufrufe nachziehen
status: todo
depends_on: [009-005-0001]
---

# Die entfallenen DBAL-Aufrufe nachziehen

## Context
Die gemessenen Bruchstellen aus `009-005-0001` beheben. Nach heutigem Stand sind es neun
Stellen, alle in `Classes/Api.php`:

| Alt | Neu | Fundstellen |
|---|---|---|
| `Connection::fetchAll()` | `fetchAllAssociative()` | 2 |
| `Connection::fetchAssoc()` | `fetchAssociative()` | 1 |
| `Connection::exec()` | `executeStatement()` | 1 |
| `QueryBuilder::execute()->fetchAll()` | `executeQuery()->fetchAllAssociative()` | 2 |
| `Connection::executeQuery()->rowCount()` | bleibt, Rückgabetyp geändert | 3 |

**Die `rowCount()`-Stellen sind die heiklen.** In DBAL 2 liefert `executeQuery()` ein
`Statement`, in DBAL 3 ein `Result`; `rowCount()` ist bei einem SELECT auf beiden nicht
zuverlässig definiert. Die drei Stellen zählen Datensätze für `/api/count` — ob sie das heute
richtig tun, ist beim Anfassen zu prüfen und nicht anzunehmen.

`exec('SET FOREIGN_KEY_CHECKS = 0;')` ist die einzige Stelle, an der das Framework
Fremdschlüssel abschaltet. Sie gehört gelesen, bevor sie umgeschrieben wird.

## Acceptance criteria
- [ ] Jede gemessene Bruchstelle ist behoben; keine davon durch Unterdrücken einer Meldung.
- [ ] Die drei `rowCount()`-Stellen sind auf ihre Richtigkeit **geprüft**, nicht nur auf ihre
      Lauffähigkeit. Zählen sie falsch, ist das ein Befund und wird als solcher festgehalten.
- [ ] Kein Verhalten ändert sich: Die Suite aus Epic `008` ist grün, ohne inhaltliche Änderung
      an einer Zusicherung.
- [ ] Wo eine Zusicherung doch angepasst werden muss, steht die Begründung je Zusicherung.

## Verification
Volle Suite grün gegen eine frisch installierte Wegwerf-Datenbank, plus die
Deprecation-Prüfung: DBAL 3 meldet über `doctrine/deprecations`, und was dort auftaucht, gehört
in die Bewertung.
