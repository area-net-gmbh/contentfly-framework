---
id: 014-001-0003
title: Routing und Metadaten auf Englisch
status: todo
depends_on: [014-001-0002]
---

# Routing und Metadaten auf Englisch

## Context
Umfang: `Kernel/Routing/` und `Classes/Metadaten/`.
- `Routensammlung` → `RouteCollector` mit `collection()` statt `sammlung()`.
- `Routeneintrag` → `RouteEntry`.
- `AbsicherungListener` → `RouteSecurityListener`.
- `ControllerResolver`: Meldungen und Kommentare.
- Das Verzeichnis `Metadaten/` → `Metadata/`, `Metadatenleser` → `MetadataReader` mit
  `forClass()` und `forProperty()`.

Die Aufrufer liegen vor allem in den Controller-Providern (`Classes/Controller/Provider/**`) und
im `RouteManager`. Dort werden nur die Aufrufe umgestellt; deren übrige Namen und Kommentare
gehören zu `014-003`.

## Acceptance criteria
- [ ] In den Dateien des Tasks steht kein deutsches Wort mehr: Bezeichner, Strings und Kommentare.
- [ ] Kommentare sind übersetzt, nicht gekürzt. Verweise auf Work-Item-IDs und `an_project/docs` bleiben.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Eine Suche nach jedem alten Namen findet nichts mehr außer in `an_project/` und `CHANGELOG.md`.
- [ ] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl wie vorher, PHPStan `[OK]`, Deprecation-Gate 0, `php bin/console.php list` läuft, `custom/config.php` wiederhergestellt.

## Verification
Vor dem Task: Test- und Assertion-Zahl der vollen Suite notieren. Danach: volle Suite gegen
`contentfly-db-0004`, PHPStan mit `--memory-limit=512M`, `tools/ci/deprecations-pruefen.sh`,
`grep -rnw` nach jedem alten Namen, `sh tools/check-template-config.sh`.
