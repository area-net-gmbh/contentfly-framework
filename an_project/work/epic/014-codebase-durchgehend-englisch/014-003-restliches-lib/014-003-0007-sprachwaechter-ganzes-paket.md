---
id: 014-003-0007
title: Sprachwächter auf das ganze Framework-Paket
status: todo
depends_on: [014-003-0006]
---

# Sprachwächter auf das ganze Framework-Paket

## Context
Nach dieser Story ist `lib/contentfly/` vollständig englisch. Der Sprachwächter prüft dann das
ganze Paket statt einzelner Verzeichnisse.

## Acceptance criteria
- [ ] `PATHS` in `tests/Unit/EnglishOnlyTest.php` enthält `lib/contentfly` als Ganzes; die bisherigen Einzelpfade darunter sind zusammengefasst.
- [ ] Alle Ausnahmen, die `014-003` betreffen, sind gestrichen; übrig sind nur Ausnahmen mit `014-004` oder `014-005`.
- [ ] Der Test ist grün. Ein Mutationstest in einer Datei, die bisher nicht geprüft wurde (etwa `Classes/Api.php`), macht ihn rot.

## Verification
Unit-Suite grün, Mutation in `Classes/Api.php` rot mit Datei und Zeile, zurückgesetzt, wieder grün. Volle Suite grün.
