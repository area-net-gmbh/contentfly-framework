---
id: 008-002-0002
title: update gegen replace abgrenzen
status: todo
depends_on: [008-002-0001]
---

# update gegen replace abgrenzen

## Context
Zwei Endpunkte, die beide ein bestehendes Objekt ändern, und **nirgends steht, worin sie sich
unterscheiden**. Ein Client, der den falschen wählt, verliert Daten. Beim Kernel-Tausch ist das
eine Stelle, an der eine Abweichung niemandem auffällt, bis es zu spät ist.

## Umfang

Der Kern dieses Tasks ist ein einziger Vergleich, sauber aufgesetzt:

1. Ein Objekt mit **mehreren** gefüllten Feldern anlegen.
2. Über `/api/update` **ein** Feld ändern → was ist mit den anderen?
3. Dasselbe über `/api/replace` → was ist mit den anderen?

Die Differenz ist das Ergebnis des Tasks. Sie gehört als Zusicherung festgehalten **und** im
Testkommentar in einem Satz erklärt, damit man sie nicht jedes Mal neu herleiten muss.

Dazu:
- **Was `modified` tut** — wird es bei beiden aktualisiert?
- **Was mit verjointen Feldern passiert**, wenn sie im Request fehlen.
- **Fehlerfälle**: unbekannte ID, unbekannte Entity, fehlender Token.

## Acceptance criteria
- [ ] Ein Test belegt für `/api/update`, welche nicht mitgesendeten Felder unverändert bleiben.
- [ ] Ein Test belegt dasselbe für `/api/replace`.
- [ ] Der Unterschied zwischen beiden ist als Zusicherung festgehalten und im Kommentar in
      einem Satz benannt.
- [ ] Das Verhalten von `modified` ist für beide festgehalten.
- [ ] Je Route mindestens ein Fehlerfall, in seiner heutigen Form.
- [ ] Beide Routen weisen ohne Token ab.

## Verification
Mehrere vollständige Läufe grün. Zusätzlich: Der Unterschied zwischen `update` und `replace`
muss aus den Testnamen und ihren Meldungen **ablesbar** sein, ohne den Code von `Api` zu öffnen —
das ist der eigentliche Zweck dieses Tasks.
