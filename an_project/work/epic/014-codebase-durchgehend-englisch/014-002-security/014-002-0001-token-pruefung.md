---
id: 014-002-0001
title: Token-Prüfung auf Englisch
status: review
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
- [x] Die Klassen des Tasks sind vollständig englisch, mit Namen nach der Tabelle in Epic `014`, Methoden, Variablen, Meldungen und Kommentaren.
- [x] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Testklassen, die nach einer umbenannten Klasse heissen, sind mit umbenannt; ihre Methodennamen und Kommentare folgen in `014-005`.
- [x] Eine Suche nach jedem alten Namen findet keinen Code-Treffer mehr ausser in `an_project/` und `CHANGELOG.md`.
- [x] Die Ausnahmen in `tests/Unit/EnglishOnlyTest.php`, die dieser Task auflöst, sind gestrichen.
- [x] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan mit `--memory-limit=512M`,
`tools/ci/deprecations-pruefen.sh`, `grep -rnw` nach jedem alten Namen, Suche nach deutschen Wörtern
in den Dateien des Tasks, `sh tools/check-template-config.sh`.

## Ergebnis

**Sieben Klassen umbenannt und vollständig englisch:**
- `Tokenquellen` → `TokenSources` (`chain()`, `sources()`)
- `RohkopfExtractor` → `RawHeaderExtractor`, `RumpfExtractor` → `BodyExtractor`
- `Benutzerlader` → `UserLoader`
- `Anmeldetreiber` → `TokenAuthenticator` (`user()`)
- `Tokenhandler` → `TokenHandler`, mit `lastToken()`, `lastClaims()`, `timeoutApplies()`,
  `timeoutFor()`, `isExpired()` und `REJECTION = 'Invalid token.'`
- `Zugangstoken` → `JwtAccessToken`, mit `issue()`, `isConfigured()`, `secret()`, `keyId()`,
  `verificationKeys()`, `ttl()`, `ISSUER` und `ALGORITHM`

Dazu kommen die Container-Schlüssel `tokenHandler` und `tokenAuthenticator` sowie die
Kommentare der fünf JWT-Keys in `Classes/Config.php`.

**Aufrufer:** `bootstrap.php`, `BaseControllerProvider`, `AuthControllerProvider`,
`AuthController`, `ProviderAbgleichCommand`, die Entities `Token` und `RevokedToken`
(Kommentare), fünf umbenannte Testklassen, `SchluesselwechselTest`, `AuthApiTest`,
`SystemControllerApiTest`, `ContainerSchluesselTest` und `EnglishOnlyTest`. Im Sprachwächter
sind sechs Ausnahmen gestrichen.

**Zwei Tests hängen an Dingen ausserhalb des Codes, beide nachgezogen:**
- `ContainerSchluesselTest` gleicht die internen Container-Schlüssel mit `dev-guide.md` ab.
  Dort stehen jetzt `tokenAuthenticator` und `tokenHandler`. Die übrigen Namen im dev-guide
  folgen in `014-006`.
- `SystemControllerApiTest` prüft eine Quelltextzeile in `TokenHandler.php`. Sie heisst jetzt
  `$this->em->remove($row);`.

**Ein PHPStan-Fehlalarm, gefunden und abgestellt:** Nach der Umbenennung `Tokenhandler.php` →
`TokenHandler.php`, die sich auf macOS nur in der Gross- und Kleinschreibung unterscheidet, meldete
PHPStan zwölf Fehler. Es waren dieselben sechs doppelt, unter beiden Schreibweisen, und darunter
„return statement is missing" für Methoden, die eines haben. Nach `phpstan clear-result-cache`:
`[OK] No errors`. Das Suite-Skript leert den Cache seitdem vor jedem Lauf.

**Beim Umbenennen der Testdateien** ist eine Schleife an der Wortaufteilung der zsh gescheitert.
Die Klassen in den Dateien waren schon umbenannt, die Dateinamen noch nicht. Die Umbenennung ist
einzeln nachgeholt.

Geprüft: volle Suite `OK (528 tests, 1699 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün, `console list` läuft. Die Suche nach deutschen Wörtern in den sieben
Klassen findet nichts.
