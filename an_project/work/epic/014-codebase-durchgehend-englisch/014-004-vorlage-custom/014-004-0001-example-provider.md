---
id: 014-004-0001
title: Beispiel-Provider der Vorlage auf Englisch
status: todo
depends_on: []
---

# Beispiel-Provider der Vorlage auf Englisch

## Context
Die Provider-Vorlage ist die letzte deutsche Klasse, die ein Projekt übernimmt.

Umfang nach der Tabelle in Epic `014`:
- `Custom\Classes\Anmeldung\BeispielProvider` → `Custom\Classes\Authentication\ExampleProvider`
  (Verzeichnis `custom/Classes/Authentication/`)
- Providername `beispiel` → `example` in `custom/app.php`
- Umgebungsvariable `CONTENTFLY_BEISPIEL_PROVIDER` → `CONTENTFLY_EXAMPLE_PROVIDER`, samt Konstante
  `UMGEBUNGSVARIABLE` → `ENVIRONMENT_VARIABLE`
- Variablen und Array-Schlüssel (`kennung`, `geheimnis`, `gruppen`, `teile`, `bekannte()` …)
- der Kommentar-Verweis `Custom\Classes\Anmeldung\MySsoProvider` in `custom/app.php`

Aufrufer: `tests/Integration/Api/AnmeldeproviderApiTest.php`, `ProviderSyncApiTest.php`,
`RouteAndConsoleManagerTest.php`, `tools/ci/prepare-test-environment.sh` und `.gitlab-ci.yml`.
Das Suite-Skript setzt die Variable schon unter beiden Namen.

## Acceptance criteria
- [ ] Die Dateien des Tasks sind vollständig englisch: Namen, Variablen, Schlüssel, Strings und Kommentare.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`), soweit nötig auch `dev-guide.md`, wenn ein Test daran abgleicht.
- [ ] Verhalten unverändert: volle Suite mit gleicher Testzahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan (Result-Cache geleert),
Deprecation-Gate, Nachsuche nach jedem alten Namen, Detektor des Sprachwächters über die Dateien des
Tasks, `sh tools/check-template-config.sh`.
