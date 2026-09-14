---
id: 014-004-0003
title: Sprachwächter um Vorlage und Root-Manifest erweitern
status: done
depends_on: [014-004-0002]
---

# Sprachwächter um Vorlage und Root-Manifest erweitern

## Context
Nach dieser Story sind `custom/` und das Root-Manifest englisch. Der Sprachwächter prüft beides.

## Acceptance criteria
- [x] `custom` und `composer.json` stehen in `PATHS` von `tests/Unit/EnglishOnlyTest.php`.
- [x] Übrig sind nur Ausnahmen mit `014-005` und die dauerhafte Altdaten-Ausnahme.
- [x] Der Test ist grün. Eine Mutation in `custom/app.php` macht ihn rot.

## Verification
Unit-Suite grün, Mutation in `custom/app.php` rot mit Datei und Zeile, zurückgesetzt, wieder grün. Volle Suite grün.

## Ergebnis

**`custom` und `composer.json` stehen in `PATHS`.** Damit prüft der Wächter auch das Beispiel-Template
`custom/Views/partials/_email_layout.twig` (Endung `twig`); dort ist nichts Deutsches.

**Eine neue Ausnahme mit `014-005`:** `custom/app.php` verweist im Kommentar auf den Testnamen
`HookReihenfolgeTest`. Übrig sind damit sieben Ausnahmen mit `014-005` und die dauerhafte
Altdaten-Ausnahme `'Gelöscht'`.

**Mutationstest:** Ein deutscher Kommentar in `custom/app.php` macht den Test rot:
`custom/app.php:61 [word "fuer"]`. Dass die Datei wirklich geändert war, zeigt `git diff --stat`.
Zurückgesetzt, die Datei ist unverändert und der Test grün.

Geprüft: volle Suite `OK (528 tests, 1703 assertions)`, PHPStan `[OK] No errors`, Deprecation-Gate
grün.
