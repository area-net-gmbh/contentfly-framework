---
id: 014-001-0005
title: Sprachwächter als Test
status: todo
depends_on: [014-001-0004]
---

# Sprachwächter als Test

## Context
Eine einmalige Suche nach deutschen Resten schützt nur den Moment. Ein Test hält den Zustand:
`tests/Unit/EnglishOnlyTest.php` scannt eine Liste von Pfaden. Er meldet jede Datei mit einem
deutschen Wort, und zwar mit Datei, Zeile und Fundstelle.

- **Geprüft wird alles**: Bezeichner, Strings und Kommentare. Erkannt werden Umlaute, `ß` und
  eine Liste typischer deutscher Wörter in Wortgrenzen. Wörter, die auch englisch sind (`die`,
  `also`, `will` …), gehören nicht in die Liste.
- **Die Pfadliste wächst mit dem Epic.** Diese Story trägt ihren Bereich ein, jede weitere Story
  ihren, und `014-006` deckt den ganzen Baum ab.
- **Eine Ausnahmeliste für echte Fremdwörter muss sich selbst prüfen.** Eine Ausnahme, die nichts
  mehr trifft, macht den Lauf rot.
- **Mutationstest:** Ein absichtlich eingefügtes deutsches Wort in einer geprüften Datei macht den
  Test rot. Ein Test, der nichts sieht, ist keiner.

## Acceptance criteria
- [ ] `tests/Unit/EnglishOnlyTest.php` existiert, läuft in der Unit-Suite und ist grün für alle Pfade von `014-001`.
- [ ] Die Pfadliste enthält genau die Pfade dieser Story. Ein Pfad, der nicht existiert, macht den Test rot.
- [ ] Die Ausnahmeliste prüft sich selbst: Eine Ausnahme ohne Treffer macht den Test rot.
- [ ] Mutationstest belegt: Ein eingefügter Umlaut, ein deutsches Wort im Kommentar und ein deutscher Bezeichner machen den Test jeweils rot, und die Meldung nennt Datei und Zeile.
- [ ] Die Epic-Datei beschreibt, dass jede Story ihre Pfade in die Liste einträgt.

## Verification
Unit-Suite grün. Drei Mutationen einzeln einfügen, Test rot mit Datei und Zeile, zurücksetzen,
Test grün. Eine erfundene Ausnahme eintragen: rot.
