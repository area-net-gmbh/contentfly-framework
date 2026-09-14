---
id: 014-001-0001
title: Paths und Start samt Einstiegspunkten auf Englisch
status: review
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
- [x] In den Dateien des Tasks steht kein deutsches Wort mehr: Bezeichner, Strings und Kommentare.
- [x] Kommentare sind übersetzt, nicht gekürzt. Verweise auf Work-Item-IDs und `an_project/docs` bleiben.
- [x] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Eine Suche nach jedem alten Namen findet nichts mehr außer in `an_project/` und `CHANGELOG.md`.
- [x] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl wie vorher, PHPStan `[OK]`, Deprecation-Gate 0, `php bin/console.php list` läuft, `custom/config.php` wiederhergestellt.

## Verification
Vor dem Task: Test- und Assertion-Zahl der vollen Suite notieren. Danach: volle Suite gegen
`contentfly-db-0004`, PHPStan mit `--memory-limit=512M`, `tools/ci/deprecations-pruefen.sh`,
`grep -rnw` nach jedem alten Namen, `sh tools/check-template-config.sh`.

## Ergebnis

**`Pfade` heisst `Paths`, `Start::konsole()` heisst `Start::console()`, und die Konstante heisst
`CONTENTFLY_PROJECT_DIR`.** Beide Klassen sind vollständig englisch: Namen, private Methoden
(`prepare`, `assertNoSecondVendorTree`, `assertConfigurationExists`, `abort`), alle drei
Abbruchmeldungen und alle Kommentare. Das gilt ebenso für `index.php`, `bin/console.php`,
`bin/cli-config.php` und `tests/bootstrap.php`.

**Aufrufer mitgezogen:** 21 Dateien in `lib`, `custom`, `bin` und `tests`. In `custom/config.php`
sind genau zwei Zeilen geändert, die Konstante und ihr Kommentar; die `$SET_*`-Platzhalter
stehen unverändert. `PfadeTest` heisst `PathsTest`, weil die Testdatei dem Klassennamen folgt.
Die Methodennamen und Kommentare dieses Tests folgen in `014-005`. Tests, die Meldungstexte
prüfen, prüfen jetzt den englischen Text mit derselben Zusicherung.

**Eine Falle beim Ersetzen, sofort gefunden:** Wo der voll qualifizierte Name stand
(`\Areanet\PIM\Classes\Kernel\Pfade::setzen`), griff zuerst die Regel für den
Namensraum. Danach passte das Muster `Pfade::setzen(` nicht mehr, und an drei Stellen stand
`Paths::setzen()`. Die Nachsuche nach `Paths::<deutscher Methodenname>` hat sie gefunden.

**Ein deutsches Wort in einer Meldung entfernt:** Die Abbruchmeldung verwies auf den Abschnitt
„Paketgrenze" in `breaking-changes.md`. Sie nennt jetzt nur die Datei. Ein deutscher
Abschnittsname in einer englischen Meldung wäre genau die Mischung, um die es geht.

**Suche nach alten Namen:** Kein Code-Treffer mehr für `Pfade::`, `Kernel\Pfade`,
`Start::konsole` und `CONTENTFLY_PROJEKT`. Das deutsche Wort „Pfade" steht noch in der Prosa von
drei Kommentaren (`BaseControllerProvider`, `PluginManagerTest`, `RouteAndConsoleManagerTest`),
und `IntegrationTestCase::konsole()` ist ein Testhelfer. Beides gehört zu `014-003` bzw.
`014-005`.

Geprüft: volle Suite `OK (524 tests, 1688 assertions)`, dieselben Zahlen wie vor dem Task.
PHPStan `[OK] No errors`, Deprecation-Gate grün, `check-template-config.sh` grün,
`console list` läuft. Die Suche nach deutschen Wörtern in den sechs Dateien des Tasks findet
nichts; eine absichtlich deutsche Datei macht sie rot.
