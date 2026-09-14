---
id: 014-004-0001
title: Beispiel-Provider der Vorlage auf Englisch
status: done
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
- [x] Die Dateien des Tasks sind vollständig englisch: Namen, Variablen, Schlüssel, Strings und Kommentare.
- [x] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`), soweit nötig auch `dev-guide.md`, wenn ein Test daran abgleicht.
- [x] Verhalten unverändert: volle Suite mit gleicher Testzahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan (Result-Cache geleert),
Deprecation-Gate, Nachsuche nach jedem alten Namen, Detektor des Sprachwächters über die Dateien des
Tasks, `sh tools/check-template-config.sh`.

## Ergebnis

**Die Provider-Vorlage heisst `Custom\Classes\Authentication\ExampleProvider`** und liegt unter
`custom/Classes/Authentication/`. Weitere Namen:
- Providername `example`
- Umgebungsvariable `CONTENTFLY_EXAMPLE_PROVIDER`, Konstante `ENVIRONMENT_VARIABLE`
- Variablen und Array-Schlüssel: `identifier`, `secret`, `groups`, `knownEntries()`, `$entry`,
  `$parts` und weitere
- im PHPStan-Typ `list<array{identifier: string, secret: string, groups: list<string>}>`

**Aufrufer:**
- `custom/app.php`: `register('example', …)` und der Kommentarverweis auf
  `Custom\Classes\Authentication\MySsoProvider`
- `tools/ci/prepare-test-environment.sh` und `.gitlab-ci.yml`: die Umgebungsvariable für den
  Testserver
- `AnmeldeproviderApiTest` und `ProviderSyncApiTest`: `loginManager => example`, der erwartete
  Alias `example:…` und die Konstante
- `tests/README.md`

**Der Nachweis ist belastbar:** Das lokale Suite-Skript setzte die Variable bisher unter beiden
Namen. Für diesen Lauf setzt es nur noch `CONTENTFLY_EXAMPLE_PROVIDER`. Die Provider-Tests sind
damit nachweislich über den neuen Namen grün und nicht über einen liegengebliebenen alten.

Geprüft: volle Suite `OK (528 tests, 1703 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün. Der Detektor findet in `ExampleProvider.php` nichts.
