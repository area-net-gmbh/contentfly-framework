---
id: 014-005-0001
title: Test-Infrastruktur auf Englisch
status: todo
depends_on: []
---

# Test-Infrastruktur auf Englisch

## Context
Alle Integrationstests rufen die Helfer aus `IntegrationTestCase`. Die Infrastruktur kommt deshalb
zuerst, und ihre Umbenennungen werden in allen Aufrufern mitgezogen.

Umfang:
- `tests/Integration/IntegrationTestCase.php` mit allen Helfern (`nachTestLoeschen()`,
  `benutzerAnlegen()`, `konsole()`, `datenverzeichnis()`, `anwendungsverzeichnis()` …)
- `tests/router.php`
- `tests/Integration/UmgebungsWaechterTest.php` → englischer Klassenname
- `tests/Integration/VersandfalleTest.php` → englischer Klassenname
- Test-Umgebungsvariable `CONTENTFLY_TEST_PROJEKT` → `CONTENTFLY_TEST_PROJECT_DIR`
- `tests/README.md`

In den übrigen Testdateien werden nur die Aufrufe der umbenannten Helfer umgestellt.

## Acceptance criteria
- [ ] Die Dateien des Tasks sind vollständig englisch: Klassen-, Methoden- und Helfernamen, Variablen, Assertion-Meldungen, Strings und Kommentare. Testklassen, deren Name deutsch ist, sind samt Datei umbenannt.
- [ ] Die Prüfungen sind unverändert: Je Datei sind Test- und Assertion-Zahl vor und nach dem Task gleich. Die Ergebnis-Notiz enthält die Liste alt → neu aller umbenannten Testmethoden und -klassen.
- [ ] Werte, die ein Test gegen Framework-Code oder gegen die Datenbank prüft (Quelltextzeilen, Meldungen, Tabellennamen), stimmen weiterhin mit dem Framework überein.
- [ ] Alle Verweise auf umbenannte Tests und Helfer im Baum sind mitgezogen (`lib`, `custom`, `tests`, `tools`, `phpunit.xml.dist`, `rector.php`).
- [ ] Volle Suite mit gleicher Gesamtzahl an Tests und Assertions, PHPStan `[OK]`, Deprecation-Gate grün.

## Verification
Vor dem Task: je Datei `phpunit <datei>` (Integrationstests gegen den laufenden Testserver) mit
Test- und Assertion-Zahl notieren. Danach dieselben Läufe, Zahlen vergleichen. Volle Suite, PHPStan
(Result-Cache geleert), Deprecation-Gate, Detektor des Sprachwächters über die Dateien des Tasks.
