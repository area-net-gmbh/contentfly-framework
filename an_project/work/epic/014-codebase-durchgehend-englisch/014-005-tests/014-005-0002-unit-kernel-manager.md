---
id: 014-005-0002
title: Unit-Tests Kernel, Manager, Entity, Service und Ci auf Englisch
status: todo
depends_on: [014-005-0001]
---

# Unit-Tests Kernel, Manager, Entity, Service und Ci auf Englisch

## Context
Umfang: `tests/Unit/AutoloaderUeberschneidungTest.php`, alle Dateien unter `tests/Unit/Kernel/`,
`tests/Unit/Manager/`, `tests/Unit/Entity/`, `tests/Unit/Service/` und `tests/Unit/Ci/`.

Deutsch benannte Klassen bekommen englische Namen, darunter `AutoloaderUeberschneidungTest`,
`KeineSilexTypenTest`, `PaketmanifestTest`, `HookReihenfolgeTest`, `RoutenNamenTest`,
`LoginProviderAufloesungTest` und `CiSchritteTest`. Die Verweise darauf in `lib/`, `custom/` und
den Ausnahmen des Sprachwächters ziehen mit.

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
