---
id: 014-003-0003
title: ApiController auf Englisch
status: todo
depends_on: [014-003-0002]
---

# ApiController auf Englisch

## Context
`Controller/ApiController.php` hat 1.236 Zeilen und mit rund 170 markierten Zeilen die höchste
Dichte deutscher Texte im Paket. Er bekommt deshalb einen eigenen Task.

Umfang: alle Variablen, Meldungen, apidoc-Blöcke und Kommentare. Die Actions und Routen sind
englisch und bleiben.

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
