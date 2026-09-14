---
id: 014-005-0005
title: Integrationstests Teil 2 auf Englisch
status: todo
depends_on: [014-005-0004]
---

# Integrationstests Teil 2 auf Englisch

## Context
Umfang: die übrigen Integrationstests unter `tests/Integration/Api/`:
`ConstraintApiTest`, `FileApiTest`, `LogSideEffectApiTest`, `ManyToManyApiTest`,
`MultiupdateApiTest`, `QueryApiTest`, `ReadApiTest`, `ReadPermissionApiTest`,
`RouteSecurityApiTest`, `SyncApiTest`, `TreeApiTest`, `UnenforcedPermissionApiTest`,
`UpdateReplaceApiTest`, `WriteApiTest` und `WritePermissionApiTest`.

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
