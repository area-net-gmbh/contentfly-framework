---
id: 014-002-0001
title: Token-Prüfung auf Englisch
status: todo
depends_on: []
---

# Token-Prüfung auf Englisch

## Context
Der Weg, auf dem ein Request sich ausweist, ist der Kern der Authentifizierung und das Erste,
was die IT-Security liest.

Umfang nach der Tabelle in Epic `014`:
- `Tokenquellen` → `TokenSources` mit `chain()` und `sources()`
- `RohkopfExtractor` → `RawHeaderExtractor`, `RumpfExtractor` → `BodyExtractor`
- `Tokenhandler` → `TokenHandler`, samt öffentlicher Methoden (`letzterToken()`, `letzteClaims()`,
  `timeoutGilt()` …) und des Container-Schlüssels `tokenHandler`
- `Anmeldetreiber` → `TokenAuthenticator` mit `user()`, Container-Schlüssel `tokenAuthenticator`
- `Benutzerlader` → `UserLoader`
- `Zugangstoken` → `JwtAccessToken` mit `issue()`, dazu die übrigen Methoden (`eingerichtet()`,
  `pruefschluessel()`, `lebensdauer()` …)
- Die JWT-Config-Keys sind schon englisch. Ihre Kommentare in `Classes/Config.php` werden hier
  mit übersetzt.

## Acceptance criteria
- [ ] Die Klassen des Tasks sind vollständig englisch, mit Namen nach der Tabelle in Epic `014`, Methoden, Variablen, Meldungen und Kommentaren.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Testklassen, die nach einer umbenannten Klasse heissen, sind mit umbenannt; ihre Methodennamen und Kommentare folgen in `014-005`.
- [ ] Eine Suche nach jedem alten Namen findet keinen Code-Treffer mehr ausser in `an_project/` und `CHANGELOG.md`.
- [ ] Die Ausnahmen in `tests/Unit/EnglishOnlyTest.php`, die dieser Task auflöst, sind gestrichen.
- [ ] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan mit `--memory-limit=512M`,
`tools/ci/deprecations-pruefen.sh`, `grep -rnw` nach jedem alten Namen, Suche nach deutschen Wörtern
in den Dateien des Tasks, `sh tools/check-template-config.sh`.
