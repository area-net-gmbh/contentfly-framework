---
id: 014-005-0003
title: Unit-Tests Security und Migration samt Fixtures auf Englisch
status: todo
depends_on: [014-005-0002]
---

# Unit-Tests Security und Migration samt Fixtures auf Englisch

## Context
Umfang: alle Dateien unter `tests/Unit/Security/` und `tests/Unit/Migration/` sowie die Fixtures
unter `tests/Fixtures/RectorMigration/`.

- `SchluesselwechselTest` → englischer Klassenname
- `MigrationsleitfadenTest` und `RectorRegelTest` → englische Klassennamen
- Fixture-Verzeichnisse `alt/` und `soll/` → `before/` und `after/`
- Fixture-Klassen `Artikel`, `Rubrik` und `AndererAlias` → englische Namen, samt Aliasen

**Achtung:** `RectorRegelTest` fährt die Rector-Regel gegen die Fixtures und vergleicht mit dem
Soll-Stand Zeichen für Zeichen. Umbenennungen müssen in `before/` und `after/` identisch sein.

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
