---
id: 014-002-0004
title: AuthController vollständig englisch
status: todo
depends_on: [014-002-0003]
---

# AuthController vollständig englisch

## Context
`Controller/AuthController.php` hat 513 Zeilen und ist der Login-Endpunkt. Nach den Tasks 0001
bis 0003 ruft er nur noch englische Klassen. Seine eigenen Variablen, Meldungen und Kommentare
sind aber noch deutsch.

Umfang: alle lokalen Variablen (`$kennung`, `$anbieterName`, `$vorgezeigt` …), alle
Fehlermeldungen und alle Kommentare. Dazu kommt der tote Import `use
Areanet\PIM\Classes\LoginProvider`: Die Klasse existiert nicht, und nach `0002` gibt es
`Classes\Security\LoginProvider`. Der Import stiftet damit Verwechslung und wird entfernt.

Meldungen, die die API an Clients schickt, dürfen sich im Text ändern. Status und Envelope bleiben
gleich. Tests, die einen Meldungstext prüfen, prüfen danach den englischen Text.

## Acceptance criteria
- [ ] Die Klassen des Tasks sind vollständig englisch, mit Namen nach der Tabelle in Epic `014`, Methoden, Variablen, Meldungen und Kommentaren.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Testklassen, die nach einer umbenannten Klasse heissen, sind mit umbenannt; ihre Methodennamen und Kommentare folgen in `014-005`.
- [ ] Eine Suche nach jedem alten Namen findet keinen Code-Treffer mehr ausser in `an_project/` und `CHANGELOG.md`.
- [ ] Die Ausnahmen in `tests/Unit/EnglishOnlyTest.php`, die dieser Task auflöst, sind gestrichen.
- [ ] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan mit `--memory-limit=512M`,
`tools/ci/deprecations-pruefen.sh`, `grep -rnw` nach jedem alten Namen, Suche nach deutschen Wörtern
in den Dateien des Tasks, `sh tools/check-template-config.sh`.
