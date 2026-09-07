---
id: 008-003-0002
title: "Lesen: isReadable in allen vier Stufen"
status: done
depends_on: [008-003-0001]
---

# Lesen: isReadable in allen vier Stufen

## Context
`Permission::isReadable()` entscheidet, welche Objekte ein Benutzer überhaupt sieht. Beim
Kernel-Tausch ist das die Stelle, an der ein Fehler **nicht sichtbar bricht, sondern still zu
viel herausgibt** — die gefährlichste Sorte Regression.

## Umfang

### Die vier Stufen
Je Stufe ist festzuhalten, was `/api/list` und `/api/single` liefern:

| Stufe | erwartet festzuhalten |
|---|---|
| `ALL` (2) | alle Objekte |
| `GROUP` (3) | nur Objekte, deren Ersteller in derselben Gruppe ist oder die die Gruppe in `groups` führen |
| `OWN` (1) | nur Objekte mit `userCreated = ich` oder die mich in `users` führen |
| kein Recht | **gefilterte Liste oder Fehler?** Das ist offen und gehört gemessen. |

Der letzte Fall ist der wichtigste: `Api::getList()` überspringt eine Entity ohne Leserecht
(`continue`), `Api::getSingle()` wirft. Ob daraus eine leere Liste oder ein Fehler wird, ist
festzuhalten, nicht zu erraten.

### `pim_blocked` — verjointe Objekte ohne Leserecht
Ist ein verjointes Objekt nicht lesbar, liefern die Typ-Klassen statt des Objekts:

```php
array('id' => $subobject->getId(), 'pim_blocked' => true)
```

Das Verhalten ist über **fünf** Klassen verteilt — `JoinType`, `RadioType`, `OnejoinType`,
`CheckboxType`, `MultijoinType` — und nirgends festgehalten. Mindestens zwei davon sind
abzudecken; welche, entscheidet, was sich mit den vorhandenen Entities aufbauen lässt
(`PIM\Tag.userCreated` ist ein `join`, `PIM\User.group` ebenfalls).

Wichtig ist der Nachweis, dass **die Id trotzdem durchgereicht wird** — der Client erfährt, dass
da etwas ist, aber nicht was. Ob das richtig ist, steht hier nicht zur Debatte; es ist der
Ist-Zustand.

### Ein Detail, das auffiel
`Permission::is()` liefert `2` für Admins, `0` wenn der Benutzer keine Gruppe hat, den
Spaltenwert bei einem Treffer — und **`false`**, wenn die Gruppe keine passende Zeile hat. Zwei
verschiedene Falsy-Werte für zwei verschiedene „nein". Festhalten, nicht angleichen.

## Acceptance criteria
- [x] Für jede der vier Stufen ist durch einen Test festgehalten, was `/api/list` zurückgibt.
- [x] Für `OWN` und `GROUP` ist die Filterung an konkreten Objekten belegt — je eines, das
      sichtbar sein muss, und eines, das es nicht sein darf.
- [x] Das Verhalten von `/api/single` ohne Leserecht ist festgehalten.
- [x] `pim_blocked` ist für mindestens zwei Typ-Klassen geprüft, inklusive der durchgereichten Id.
- [x] Ein Test hält fest, dass ein Admin alle Stufen übergeht.

## Verification
Mehrere vollständige Läufe. Für `OWN` und `GROUP` muss der Test **beide** Richtungen zeigen —
ein Test, der nur belegt, dass etwas sichtbar ist, würde einen zu weit geöffneten Filter nicht
bemerken.

## Ergebnis — 10 Tests in `tests/Integration/Api/ReadPermissionApiTest.php`

Gesamtsuite: **119 Tests, 296 Assertions**, drei Läufe grün.

### Die offene Frage ist beantwortet: es ist ein Fehler, keine leere Liste

Der Task sollte messen, ob eine Entity ohne Leserecht als **gefilterte leere Liste** oder als
**Fehler** herauskommt. Antwort: `Api::getList()` wirft
`contentfly_general_permission_denied` (Api.php:1050), `Api::getSingle()` wirft
`contentfly_general_access_denied`. Beides landet heute als HTTP 500 statt 403
(`000-000-0006`).

Das ist die sichere Variante — ein Fehler ist schwerer zu übersehen als eine unerklärt leere
Liste.

### Alle vier Stufen, jeweils in beiden Richtungen

| Stufe | belegt |
|---|---|
| `ALL` (2) | Eigenes **und** Fremdes sichtbar |
| `OWN` (1) | Eigenes sichtbar, Fremdes **nicht** — dazu: ein fremdes Objekt wird sichtbar, sobald es mich in `users` führt |
| `GROUP` (3) | Eigenes und das über `groups` geteilte sichtbar, Unbeteiligtes **nicht** |
| kein Recht | Fehler, keine Daten |

Dazu zwei Randfälle: Ein Benutzer **ohne Gruppe** sieht nichts (`Permission::is()` liefert `0`,
bevor überhaupt Berechtigungszeilen betrachtet werden), und ein **Admin** übergeht alle Stufen.

Jede Stufe ist in **beiden** Richtungen geprüft. Ein Test, der nur die Sichtbarkeit belegt,
würde einen zu weit geöffneten Filter nicht bemerken — und genau so sieht ein Fehler an dieser
Stelle aus.

### `pim_blocked`
`PIM\Tag.userCreated` ist ein `join` auf `PIM\User`. Wer Tags lesen darf, Benutzer aber nicht,
bekommt exakt `array('id' => …, 'pim_blocked' => true)` — die Id wird durchgereicht, das
Objekt nicht. Die Gegenrichtung ist mitgeprüft: mit Leserecht auf `PIM\User` fällt die
Markierung weg und das Objekt kommt ganz.

### Ein Fund im eigenen Test
Der erste Lauf scheiterte neunmal an einem SQL-Syntaxfehler: Meine `INSERT`-Anweisung schrieb
`groups` ohne Backticks — **dasselbe reservierte Wort in MySQL 8**, das ich in `012-005-0003`
in `Api::getTree2()` gequotet habe. Die Falle ist also nicht auf Produktionscode beschränkt;
der Kommentar im Test verweist jetzt darauf.

## Verification
- [x] Drei vollständige Läufe grün bei zufälliger Ausführungsreihenfolge.
- [x] Für `OWN` und `GROUP` zeigt je ein Test **beide** Richtungen.
- [x] Nach den Läufen: `pim_user=1` (Installations-Admin), `pim_group=0`, `pim_permission=0`,
      `pim_tag=0`.
