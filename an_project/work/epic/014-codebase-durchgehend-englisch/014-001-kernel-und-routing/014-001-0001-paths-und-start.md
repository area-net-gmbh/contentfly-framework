---
id: 014-001-0001
title: Paths und Start samt Einstiegspunkten auf Englisch
status: todo
depends_on: []
---

# Paths und Start samt Einstiegspunkten auf Englisch

## Context
`Kernel\Pfade` und `Kernel\Start` sind die Tür ins Framework, und jede andere Datei hängt an
ihnen. Deshalb kommen sie zuerst.

Umfang nach der Tabelle in Epic `014`:
- `Pfade` → `Paths` mit `project()`, `package()`, `data()`, `custom()`, `plugins()`, `set()`,
  `isSet()`, `reset()`, `frameworkEntities()` und `projectEntities()`.
- `Start::konsole()` → `Start::console()`. Die privaten Methoden (`vorbereiten`,
  `keinZweiterBaum`, `konfigurationVorhanden` …) bekommen englische Namen, die Startmeldung
  („Contentfly kann nicht starten …") wird englisch.
- Konstante `CONTENTFLY_PROJEKT` → `CONTENTFLY_PROJECT_DIR`. Betroffen sind auch
  `custom/config.php` (nur diese Stelle, die Datei bleibt Vorlage mit `$SET_*`-Platzhaltern) und
  `tests/bootstrap.php`.
- `index.php`, `bin/console.php` und `bin/cli-config.php`: Kommentare englisch.

## Acceptance criteria
- [ ] In den Dateien des Tasks steht kein deutsches Wort mehr: Bezeichner, Strings und Kommentare.
- [ ] Kommentare sind übersetzt, nicht gekürzt. Verweise auf Work-Item-IDs und `an_project/docs` bleiben.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Eine Suche nach jedem alten Namen findet nichts mehr außer in `an_project/` und `CHANGELOG.md`.
- [ ] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl wie vorher, PHPStan `[OK]`, Deprecation-Gate 0, `php bin/console.php list` läuft, `custom/config.php` wiederhergestellt.

## Verification
Vor dem Task: Test- und Assertion-Zahl der vollen Suite notieren. Danach: volle Suite gegen
`contentfly-db-0004`, PHPStan mit `--memory-limit=512M`, `tools/ci/deprecations-pruefen.sh`,
`grep -rnw` nach jedem alten Namen, `sh tools/check-template-config.sh`.
