---
id: 015-000-0004
title: bin/console.php läuft über HTTP ohne CLI-Prüfung
status: review
depends_on: []
---

# bin/console.php läuft über HTTP ohne CLI-Prüfung

## Context
**Security-Scan 2026-09, HIGH, Konfidenz mittel. Finding F7.**

Weder `bin/console.php` (Zeile 68, `$app['console']->run()`) noch `Start::console()` prüfen
`PHP_SAPI`. Im dokumentierten Layout liegt `bin/` neben `index.php` im Document Root
(`runbook.md`, `tools/ci/bezugsweg-pruefen.sh`), und `.htaccess` sperrt unter `bin/` nichts. Unter
einer Web-SAPI mit aktivem `register_argc_argv` liest Symfonys `ArgvInput` `$_SERVER['argv']` aus
dem Request. Damit läuft jeder registrierte Befehl ohne Anmeldung, darunter `appcms:setup` (setzt
das Admin-Passwort auf `admin`, siehe `015-000-0002`), `dbal:run-sql`, `orm:run-dql` und
`orm:schema-tool:drop`.

`register_argc_argv` ist PHPs eingebaute Vorgabe, wenn keine `php.ini` geladen ist, etwa in den
offiziellen Docker-Images. Die mitgelieferten `php.ini`-Vorlagen schalten es ab.

## Acceptance criteria
- [x] `bin/console.php`, `bin/cli-config.php` und `Start::console()` brechen ab, wenn `PHP_SAPI` nicht `cli` (oder `phpdbg`) ist.
- [x] `.htaccess` sperrt den Web-Zugriff auf `bin/`, `custom/`, `lib/` und `vendor/`, ebenso auf alles unter `data/` ausser den öffentlich ausgelieferten Dateien.
- [x] Die Console funktioniert auf der Kommandozeile unverändert.

## Verification
Unit-Test mit simulierter Nicht-CLI-SAPI: Der Einstieg bricht ab, bevor ein Befehl läuft. Manuell
bzw. per Integrationstest: `GET /bin/console.php` antwortet 403 bzw. 404, und
`php bin/console.php list` listet die Befehle wie bisher.
