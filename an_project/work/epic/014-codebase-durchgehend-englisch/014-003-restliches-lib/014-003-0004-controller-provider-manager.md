---
id: 014-003-0004
title: File- und SystemController, Provider und Manager auf Englisch
status: todo
depends_on: [014-003-0003]
---

# File- und SystemController, Provider und Manager auf Englisch

## Context
Umfang:
- `Controller/FileController.php` und `Controller/SystemController.php`, mit Meldungen wie
  „Schema-Cache wurde geleert!"
- `Classes/Controller/**`: `BaseController`, `BaseControllerProvider` mit `anmelden()` →
  `authenticate()` und der Meldung „Contentfly ist nicht installiert …", `Route` und die fünf
  Provider unter `Base/` mit „Zugriff verweigert"
- `Classes/Manager/**`: `RouteManager`, `ConsoleManager`, `TypeManager`, `PluginManager`
- `Classes/Command/CustomCommand.php`

## Acceptance criteria
- [ ] Die Dateien des Tasks sind vollständig englisch: Namen, Methoden, Eigenschaften, Variablen, Konstanten, Meldungen und Kommentare.
- [ ] Werte, die gespeichert oder über die API ausgeliefert werden (Tabellen- und Spaltennamen, Message-Keys, Token-`purpose`), bleiben unverändert, wenn sie schon englisch sind. Ein deutscher gespeicherter Wert wird nur nach Rückfrage geändert.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`, `dev-guide.md`, soweit ein Test daran abgleicht). Testklassen, die nach einer umbenannten Klasse heissen, sind mit umbenannt.
- [ ] Die Ausnahmen im Sprachwächter, die dieser Task auflöst, sind gestrichen.
- [ ] Verhalten unverändert: volle Suite mit gleicher Testzahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan (Result-Cache geleert),
`tools/ci/deprecations-pruefen.sh`, Nachsuche nach jedem alten Namen (auch in umgebrochenen Aufrufen),
Suche nach deutschen Wörtern in den Dateien des Tasks, `sh tools/check-template-config.sh`.
