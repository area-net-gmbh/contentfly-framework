---
id: 008-003-0003
title: "Schreiben und Löschen: isWritable, isDeletable"
status: todo
depends_on: [008-003-0001]
---

# Schreiben und Löschen: isWritable, isDeletable

## Context
Die Gegenstücke zu `isReadable`, mit dem Unterschied, dass ein Fehler hier **Daten kostet**
statt sie preiszugeben. Durchgesetzt werden sie an vier Stellen: `Api::insert()`,
`Api::update()`, `Api::delete()` und `MultijoinType` beim Setzen von Beziehungen.

## Umfang

### Die Durchsetzungspunkte
| Recht | geprüft in |
|---|---|
| `isWritable` | `Api.php:248` (insert), `Api.php:448` (update), `MultijoinType:190` und `:230` |
| `isDeletable` | `Api.php:105` (delete) |

Je Stufe (`ALL`, `GROUP`, `OWN`, kein Recht) ist festzuhalten, ob `insert`, `update` und
`delete` durchgehen.

### Der springende Punkt: gegen die Datenbank prüfen
Ein HTTP 500 sagt nichts darüber, **ob die Operation trotzdem wirkte**. Jede Zusicherung dieses
Tasks liest deshalb nach der abgewiesenen Operation die Datenbank:

- Nach einem abgewiesenen `insert`: existiert die Zeile nicht.
- Nach einem abgewiesenen `update`: steht der alte Wert da.
- Nach einem abgewiesenen `delete`: existiert die Zeile noch.

Das ist derselbe Maßstab wie in `008-002` — dort hat er gezeigt, dass der Schutz trotz falschem
Statuscode greift.

### `OWN` und `GROUP` beim Schreiben
Interessant ist der Fall, in dem ein Benutzer Schreibrecht der Stufe `OWN` hat und ein
**fremdes** Objekt ändern will. Ob `Api::update()` das prüft oder nur das Recht auf die Entity
kennt, ist festzuhalten — hier könnte eine Lücke liegen.

### MultijoinType
Beim Setzen einer ManyToMany-Beziehung prüft `MultijoinType` das Schreibrecht auf der
**Zielentity**. Ob das über die API auslösbar ist, hängt davon ab, ob eine passende Beziehung
zwischen zwei Entities mit unterschiedlichen Rechten aufgebaut werden kann. Lässt es sich nicht
auslösen, wird das begründet festgehalten — nicht mit einem Test ins Leere kaschiert.

## Acceptance criteria
- [ ] Für jede Stufe ist festgehalten, ob `insert`, `update` und `delete` durchgehen.
- [ ] **Jede abgewiesene Operation ist gegen die Datenbank geprüft** — nicht nur der Statuscode.
- [ ] Der Fall „Schreibrecht `OWN`, fremdes Objekt" ist gemessen und das Ergebnis benannt.
- [ ] Das Verhalten von `MultijoinType` ist geprüft oder die Nichtauslösbarkeit begründet.
- [ ] Findet sich eine Lücke, ist sie als eigener Task notiert, nicht hier repariert.

## Verification
Mehrere vollständige Läufe; die Testdatenbank ist danach auf dem Ausgangsstand. Für jeden
Ablehnungsfall zusätzlich der Nachweis über `pdo()`, dass die Daten unverändert sind.
