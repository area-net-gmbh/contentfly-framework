---
id: 014-002-0004
title: AuthController vollständig englisch
status: review
depends_on: [014-002-0003]
---

# AuthController vollständig englisch

## Context
`Controller/AuthController.php` hat 513 Zeilen und ist der Login-Endpunkt. Nach den Tasks 0001
bis 0003 ruft er nur noch englische Klassen. Seine eigenen Variablen, Meldungen und Kommentare
sind aber noch deutsch.

Umfang: alle lokalen Variablen (`$kennung`, `$anbieterName`, `$vorgezeigt` …), alle
Fehlermeldungen und alle Kommentare. Dazu kommt der tote Import `use
Areanet\PIM\Classes\LoginProvider`: Die Klasse existiert nicht, und nach `0002` gibt es
`Classes\Security\LoginProvider`. Der Import stiftet damit Verwechslung und wird entfernt.

Meldungen, die die API an Clients schickt, dürfen sich im Text ändern. Status und Envelope bleiben
gleich. Tests, die einen Meldungstext prüfen, prüfen danach den englischen Text.

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

**`AuthController.php` ist vollständig englisch.** Das umfasst:
- alle Kommentare und die apidoc-Blöcke
- alle lokalen Variablen: `$kennung` → `$identifier`, `$bremse` → `$throttle`, `$abweisen` →
  `$reject`, `$anbieterName` → `$providerName`, `$fremd` → `$identity`, `$zeile` → `$row`,
  `$benutzer` → `$user`, `$zugang` → `$access` und weitere
- alle Meldungen, die ein Client bekommt

| bisher | jetzt |
|---|---|
| Zu viele Anmeldeversuche. Bitte später erneut versuchen. | Too many login attempts. Please try again later. |
| Ungültiger Benutzername. | Invalid user name. |
| Benutzername und/oder Passwort fehlerhaft. | Invalid user name and/or password. |
| Der Benutzer ist gesperrt. | The user is deactivated. |
| Der Benutzer ist nur über LoginManager authorisierbar. | The user can only be authenticated through their login provider. |
| Ungültiges Refresh-Token. | Invalid refresh token. |
| JWT sind auf dieser Installation nicht eingerichtet: SECURITY_JWT_SECRET fehlt. | JWTs are not configured on this installation: SECURITY_JWT_SECRET is missing. |

Statuscodes und Envelope sind unverändert. Der Request-Parameter heisst weiterhin `loginManager`:
Bestandsclients schicken ihn so, und das ist Draht-Format, kein Bezeichner im Code.

**Der tote Import `use Areanet\PIM\Classes\LoginProvider;` ist entfernt.** Die Klasse existierte
nie. Seit `0002` gibt es `Classes\Security\LoginProvider`, und der Import hätte zu Verwechslung
eingeladen.

**Noch deutsch, weil Entity-Methoden aus `014-003`:** `Token::hashen()`, `Token::ZWECK_REFRESH`,
`istRefreshToken()`, `getKlartext()` und `User::brauchtNeuenHash()`.

**Aufrufer:** `LoginManagerApiTest` (zwei `assertSame` auf die Meldung) und
`AnmeldebremseApiTest` (Konstante `MELDUNG`) prüfen jetzt den englischen Text.

Geprüft: volle Suite `OK (528 tests, 1699 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün. Die Suche nach deutschen Wörtern im AuthController findet nichts.
