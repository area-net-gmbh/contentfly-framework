---
id: 014-001-0002
title: Container, Application, Console und Command auf Englisch
status: todo
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
- [ ] In den Dateien des Tasks steht kein deutsches Wort mehr: Bezeichner, Strings und Kommentare.
- [ ] Kommentare sind übersetzt, nicht gekürzt. Verweise auf Work-Item-IDs und `an_project/docs` bleiben.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Eine Suche nach jedem alten Namen findet nichts mehr außer in `an_project/` und `CHANGELOG.md`.
- [ ] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl wie vorher, PHPStan `[OK]`, Deprecation-Gate 0, `php bin/console.php list` läuft, `custom/config.php` wiederhergestellt.

## Verification
Vor dem Task: Test- und Assertion-Zahl der vollen Suite notieren. Danach: volle Suite gegen
`contentfly-db-0004`, PHPStan mit `--memory-limit=512M`, `tools/ci/deprecations-pruefen.sh`,
`grep -rnw` nach jedem alten Namen, `sh tools/check-template-config.sh`.
