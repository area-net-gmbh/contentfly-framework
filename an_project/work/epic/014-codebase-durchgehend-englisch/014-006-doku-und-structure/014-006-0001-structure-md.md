---
id: 014-006-0001
title: STRUCTURE.md auf die englischen Namen
status: todo
depends_on: []
---

# STRUCTURE.md auf die englischen Namen

## Context
`STRUCTURE.md` ist die englische Einstiegsdoku für die Übergabe der Codebase (`000-000-0035`). Sie
entstand vor Epic `014` und erklärt deutsche Bezeichner deshalb über ein Glossar in Abschnitt 16.
Diese Bezeichner gibt es nicht mehr. Stehen blieben 54 Treffer auf alte Namen, darunter die
Testnamen in Abschnitt 14, dazu das Glossar und der Verweis darauf in Abschnitt 2.

## Acceptance criteria
- [ ] Jeder Name in `STRUCTURE.md` stimmt mit dem Code überein: Klassen, Methoden,
  Container-Schlüssel, Config-Keys, Umgebungsvariablen, Commands, Pfade und Testklassen samt
  Testzahlen.
- [ ] Das Glossar der deutschen Bezeichner (Abschnitt 16) und der Verweis darauf in Abschnitt 2
  sind entfernt; die Nummerierung der übrigen Abschnitte bleibt stimmig.
- [ ] Jeder Code-Verweis in `STRUCTURE.md` ist gegen den Code geprüft: Klassen und Dateien
  existieren, genannte Methoden existieren in der genannten Klasse.
- [ ] `STRUCTURE.md` steht in `PATHS` von `tests/Unit/EnglishOnlyTest.php`. Nötige Ausnahmen, etwa
  Pfade deutscher Docs unter `an_project/docs/`, stehen begründet in `EXCEPTIONS`.
- [ ] Die Suche nach den alten Namen (Tabelle im Epic, umbenannte Tests und Helfer aus `014-005`)
  findet in `STRUCTURE.md` nichts.

## Verification
Suche mit der Liste der alten Namen über `STRUCTURE.md`. Skript, das alle Code-Verweise aus der
Datei zieht und gegen `lib/`, `custom/`, `tests/`, `bin/` auflöst. Testzahlen gegen
`phpunit --list-tests`. Sprachwächter grün, Mutation in `STRUCTURE.md` macht ihn rot.
