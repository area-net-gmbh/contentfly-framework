---
id: 008-003-0003
title: "Schreiben und Löschen: isWritable, isDeletable"
status: done
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
- [x] Für jede Stufe ist festgehalten, ob `insert`, `update` und `delete` durchgehen.
- [x] **Jede abgewiesene Operation ist gegen die Datenbank geprüft** — nicht nur der Statuscode.
- [x] Der Fall „Schreibrecht `OWN`, fremdes Objekt" ist gemessen und das Ergebnis benannt.
- [x] Das Verhalten von `MultijoinType` ist geprüft oder die Nichtauslösbarkeit begründet.
- [x] Findet sich eine Lücke, ist sie als eigener Task notiert, nicht hier repariert.

## Verification
Mehrere vollständige Läufe; die Testdatenbank ist danach auf dem Ausgangsstand. Für jeden
Ablehnungsfall zusätzlich der Nachweis über `pdo()`, dass die Daten unverändert sind.

## Ergebnis — 10 Tests in `tests/Integration/Api/WritePermissionApiTest.php`

Gesamtsuite: **129 Tests, 319 Assertions**, drei Läufe grün.

### Die offene Frage: **keine Lücke**

Der Task sollte messen, ob `Api::update()` bei Schreibrecht der Stufe `OWN` auch die
**Zugehörigkeit des Objekts** prüft oder nur das Recht auf die Entity kennt.

**Es prüft.** `Api.php:452` (update) und `Api.php:109` (delete) vergleichen `userCreated` gegen
den angemeldeten Benutzer und ziehen zusätzlich die `users`-Liste heran; `Api.php:456` und
`:113` tun dasselbe für `GROUP` über die `groups`-Liste. Ein fremdes Objekt bleibt unangetastet
— gegen die Datenbank geprüft, nicht nur am Statuscode.

`insert` prüft folgerichtig **nur** das Entity-Recht (`Api.php:248`): Ein neues Objekt hat
keinen Besitzer, an dem sich `OWN` messen ließe.

### Eine Sonderregel, die man leicht übersieht
`Api.php:452` trägt eine dritte Bedingung: `&& $object != $this->app['auth.user']`. Ein
Benutzer fällt damit **nie** unter die `OWN`-Sperre für sich selbst — auch dann nicht, wenn er
sich nicht selbst angelegt hat. Belegt mit einem Testbenutzer, der vom Testaufbau erzeugt wurde
und sich trotzdem ändern darf.

### Trennung von Schreiben und Löschen
Belegt: `writable = ALL` allein berechtigt **nicht** zum Löschen. Die beiden Rechte sind
unabhängig, was aus dem Schema nicht ohne Weiteres hervorgeht.

### Siebter nicht auslösbarer Pfad
`MultijoinType` prüft an zwei Stellen (Zeilen 190 und 230) das Schreibrecht auf die
Zielentity — aber nur im `mappedBy`-Zweig, der `acceptFrom` voraussetzt. Im Schema trägt
**keine einzige Eigenschaft** ein `acceptFrom`; der einzige Multijoin ist `PIM\File.tags`, und
der hat keines. Der Code-Pfad hat keinen Auslöser.

Wie bei den sechs vorigen Fällen: ein Test auf die Vorbedingung, der anschlägt, sobald eine
bidirektionale Multijoin-Beziehung entsteht.

## Verification
- [x] Drei vollständige Läufe grün bei zufälliger Ausführungsreihenfolge.
- [x] **Jede** abgewiesene Operation ist gegen die Datenbank geprüft: nichts entsteht, nichts
      ändert sich, nichts verschwindet.
- [x] Nach den Läufen: `pim_user=1`, `pim_group=0`, `pim_permission=0`, `pim_tag=0`,
      `pim_log=0`.
