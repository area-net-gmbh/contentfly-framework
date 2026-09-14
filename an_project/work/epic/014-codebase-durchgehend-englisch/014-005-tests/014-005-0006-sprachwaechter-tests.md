---
id: 014-005-0006
title: Sprachwächter auf die Testsuite
status: done
depends_on: [014-005-0005]
---

# Sprachwächter auf die Testsuite

## Context
Nach dieser Story ist `tests/` englisch. Der Sprachwächter prüft die ganze Suite, und keine Ausnahme
nennt mehr `014-005`.

## Acceptance criteria
- [x] `tests` steht in `PATHS` von `tests/Unit/EnglishOnlyTest.php`; der Test prüft sich selbst weiterhin nicht.
- [x] Keine Ausnahme nennt mehr `014-005`. ~~Übrig ist nur die dauerhafte Altdaten-Ausnahme.~~ Übrig sind dauerhafte, begründete Ausnahmen in drei Arten — Abweichung siehe Ergebnis.
- [x] Der Test ist grün. Eine Mutation in einer Testdatei macht ihn rot.

## Verification
Unit-Suite grün, Mutation in einer Testdatei rot mit Datei und Zeile, zurückgesetzt, wieder grün. Volle Suite grün.

## Ergebnis

**Der Sprachwächter prüft die ganze Testsuite.** In `PATHS` steht `tests` statt `tests/bootstrap.php`.
Die Datei des Wächters schliesst sich weiterhin selbst aus, weil sie die deutschen Wortlisten
enthält. Keine Ausnahme nennt mehr `014-005`: Die sieben Übergangsausnahmen sind in `0002` bis `0004`
mit den Umbenennungen verschwunden.

**Der erste Lauf über `tests/` meldete 25 Stellen:**

- **Acht Zitate früherer deutscher Testnamen in Kommentaren** („The test was called …"). Die
  Kommentare beschreiben jetzt, was der Test früher zusicherte, und nennen den Work-Item, in dem der
  alte Name steht (`000-000-0009`, `-0011`, `-0015`, `-0017`, `-0019`, `010-004-0002`,
  `013-001-0002`). Dass die Namen dort stehen, ist nachgeschlagen. Die Geschichte bleibt damit im
  Backlog, der Code ist englisch.
- **Zwei Überschriften deutscher Docs in `tests/README.md`** sind paraphrasiert, statt sie zu zitieren.
- **Der Rest bleibt deutsch und steht als dauerhafte Ausnahme mit Begründung im Wächter.**

**Abweichung vom Kriterium:** Es verlangte, dass nur die Altdaten-Ausnahme übrig bleibt. Das hätte
bedeutet, Werte zu ändern, die ein Test gegen etwas ausserhalb von `tests/` abgleicht. Übrig sind
deshalb drei Arten, jede dauerhaft und im Wächter so beschrieben:

| Art | Stellen |
|---|---|
| Altdaten | `'Gelöscht'` in `Api.php` und im erklärenden Kommentar von `SystemControllerApiTest`. Dazu Klartext und Schlüssel des festen Legacy-Chiffretexts in `FieldEncryptionTest`, der mit dem alten CBC-Code erzeugt wurde, und die Umlaute im Rundlauf-Wert, um die es dem Test geht. |
| Absichtlich deutsche Testeingabe | `gruppe` (mit `rolle`) auf der Liste verbotener Claim-Namen in `JwtAccessTokenTest`. |
| Abgleich mit deutschen Dateien ausserhalb der Übergabe | Skript- und Variablennamen aus `tools/ci/` (`CiStepsTest`, `tests/README.md`) sowie Sätze und Überschriften aus `an_project/docs/` (`MigrationGuideTest`, `RectorRuleTest`). |

Die dritte Art verschwindet nur, wenn `tools/ci/` und die Docs selbst englisch werden. Beides liegt
ausserhalb dieses Epics: Die Docs sind nach der Projektregel deutsch, und `tools/` gehört nicht zur
Übergabe an die Sicherheitsprüfung. Die Ausnahmen prüfen sich weiterhin selbst: Eine, die nichts
mehr trifft, macht den Test rot.

**Mutationstest:** Die Zeile `// Mutation: der Baum wird geprueft` in `TreeApiTest.php` machte den
Test rot, mit `tests/Integration/Api/TreeApiTest.php:20 [word "der"]`. Nach dem Zurücksetzen war er
grün, `git diff` leer.

**Nachweis.** Wächter `OK (4 tests, 15 assertions)`. Volle Suite `OK (528 tests, 1703 assertions)`,
je Datei gleiche Zahlen wie vor der Story. PHPStan `[OK] No errors`, Deprecation-Gate grün.
