---
id: 014-001-0002
title: Container, Application, Console und Command auf Englisch
status: done
depends_on: [014-001-0001]
---

# Container, Application, Console und Command auf Englisch

## Context
Der Container und die Anwendung tragen die öffentliche Schnittstelle für Projekte. Ihre
Meldungen sieht jeder, der einen Schlüssel falsch schreibt („Der Container kennt … nicht").

Umfang: `Kernel/Container.php`, `Application.php`, `ApplicationInterface.php`, `Console.php`,
`ConsoleEvents.php`, `ConsoleInitEvent.php`, `Command.php` und
`ControllerProviderInterface.php`.
- `Command::anwendung()` und `Console::anwendung()` → `application()`.
- `Console::projektverzeichnis()` → `projectDir()`.
- `Application::routen()` → `routes()`.
- Alle Exception-Meldungen englisch, dazu private Namen, Variablen und Kommentare.

Tests, die Meldungstexte prüfen (etwa `ContainerTest`), prüfen danach den englischen Text mit
derselben Zusicherung.

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

**Acht Dateien sind vollständig englisch:** `Container`, `Application`, `ApplicationInterface`,
`Console`, `ConsoleEvents`, `ConsoleInitEvent`, `Command` und `ControllerProviderInterface`.
Umbenannt sind `anwendung()` → `application()` in `Command` und `Console`,
`projektverzeichnis()` → `projectDir()` und `Application::routen()` → `routes()`, dazu die
Eigenschaften und lokalen Variablen (`$eintraege` → `$entries`, `$eingefroren` → `$frozen`,
`$gebootet` → `$booted` …). Alle vier Container-Meldungen und die `mount()`-Meldung sind
englisch.

**Aufrufer:** fünf Framework-Commands, `custom/Command/ExampleCommand.php` (Kommentare) und
`RoutenNamenTest`, der die Eigenschaft `routes` per Reflection liest. Kein Test prüft die
Container-Meldungen wörtlich, also war dort nichts nachzuziehen. Der private Testhelfer
`HookReihenfolgeTest::anwendung()` gehört zu `014-005`.

**Ein Fehlgriff ohne Folgen:** Der erste Übersetzungslauf für `Application.php` ist nicht
gelaufen, weil das `cd` davor ins Leere ging. Die Suche nach deutschen Wörtern hat es sofort
gezeigt, die Datei stand noch vollständig deutsch da. Wiederholt mit absolutem Pfad.

Geprüft: volle Suite `OK (524 tests, 1688 assertions)` wie vorher. PHPStan `[OK] No errors`,
Deprecation-Gate grün, `console list` läuft, `custom/config.php` unverändert. Die Suche nach
deutschen Wörtern in allen Dateien unter `Classes/Kernel/` findet nichts.
