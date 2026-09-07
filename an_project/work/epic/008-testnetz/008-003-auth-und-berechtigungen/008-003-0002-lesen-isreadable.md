---
id: 008-003-0002
title: "Lesen: isReadable in allen vier Stufen"
status: todo
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
- [ ] Für jede der vier Stufen ist durch einen Test festgehalten, was `/api/list` zurückgibt.
- [ ] Für `OWN` und `GROUP` ist die Filterung an konkreten Objekten belegt — je eines, das
      sichtbar sein muss, und eines, das es nicht sein darf.
- [ ] Das Verhalten von `/api/single` ohne Leserecht ist festgehalten.
- [ ] `pim_blocked` ist für mindestens zwei Typ-Klassen geprüft, inklusive der durchgereichten Id.
- [ ] Ein Test hält fest, dass ein Admin alle Stufen übergeht.

## Verification
Mehrere vollständige Läufe. Für `OWN` und `GROUP` muss der Test **beide** Richtungen zeigen —
ein Test, der nur belegt, dass etwas sichtbar ist, würde einen zu weit geöffneten Filter nicht
bemerken.
