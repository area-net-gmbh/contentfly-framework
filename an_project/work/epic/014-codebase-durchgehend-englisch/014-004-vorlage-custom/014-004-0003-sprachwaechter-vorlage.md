---
id: 014-004-0003
title: Sprachwächter um Vorlage und Root-Manifest erweitern
status: todo
depends_on: [014-004-0002]
---

# Sprachwächter um Vorlage und Root-Manifest erweitern

## Context
Nach dieser Story sind `custom/` und das Root-Manifest englisch. Der Sprachwächter prüft beides.

## Acceptance criteria
- [ ] `custom` und `composer.json` stehen in `PATHS` von `tests/Unit/EnglishOnlyTest.php`.
- [ ] Übrig sind nur Ausnahmen mit `014-005` und die dauerhafte Altdaten-Ausnahme.
- [ ] Der Test ist grün. Eine Mutation in `custom/app.php` macht ihn rot.

## Verification
Unit-Suite grün, Mutation in `custom/app.php` rot mit Datei und Zeile, zurückgesetzt, wieder grün. Volle Suite grün.
