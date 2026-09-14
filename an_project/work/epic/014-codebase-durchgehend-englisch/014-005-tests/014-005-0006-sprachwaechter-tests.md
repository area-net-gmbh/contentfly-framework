---
id: 014-005-0006
title: Sprachwächter auf die Testsuite
status: todo
depends_on: [014-005-0005]
---

# Sprachwächter auf die Testsuite

## Context
Nach dieser Story ist `tests/` englisch. Der Sprachwächter prüft die ganze Suite, und keine Ausnahme
nennt mehr `014-005`.

## Acceptance criteria
- [ ] `tests` steht in `PATHS` von `tests/Unit/EnglishOnlyTest.php`; der Test prüft sich selbst weiterhin nicht.
- [ ] Keine Ausnahme nennt mehr `014-005`. Übrig ist nur die dauerhafte Altdaten-Ausnahme.
- [ ] Der Test ist grün. Eine Mutation in einer Testdatei macht ihn rot.

## Verification
Unit-Suite grün, Mutation in einer Testdatei rot mit Datei und Zeile, zurückgesetzt, wieder grün. Volle Suite grün.
