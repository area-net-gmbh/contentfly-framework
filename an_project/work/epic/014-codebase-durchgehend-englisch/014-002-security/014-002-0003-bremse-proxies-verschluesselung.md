---
id: 014-002-0003
title: Anmeldebremse, Proxies und Feldverschlüsselung auf Englisch
status: review
depends_on: [014-002-0002]
---

# Anmeldebremse, Proxies und Feldverschlüsselung auf Englisch

## Context
Drei eigenständige Bausteine der Security-Schicht, die keine der anderen Klassen aufrufen.

Umfang nach der Tabelle in Epic `014`:
- `Anmeldebremse` → `LoginThrottle` mit `retryAfter()`, `recordFailure()` und `reset()`;
  Container-Schlüssel `loginThrottle`; Cache-Verzeichnis `data/cache/login-throttle`
- `VertrauteProxies` → `TrustedProxies` mit `apply()`
- `Feldverschluesselung` → `FieldEncryption` mit `encrypt()` und `decrypt()`, samt übriger
  Methoden. Aufrufer sind unter anderem `StringType`, `TextareaType` und `ReencryptCommand`

**Achtung beim Cache-Verzeichnis:** Ein vorhandenes `data/cache/loginbremse` bleibt auf der
Platte liegen. Weil der Name nie in einem Release war, braucht es keine Migration.

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

**Drei Klassen umbenannt und vollständig englisch:**
- `Anmeldebremse` → `LoginThrottle` mit `retryAfter()`, `recordFailure()` und `reset()` sowie den
  Konstanten `TIERS_IDENTIFIER` und `TIERS_IP`
- `VertrauteProxies` → `TrustedProxies` mit `apply()`, `list()`, `headerSet()` und `HEADER_SETS`
- `Feldverschluesselung` → `FieldEncryption` mit `encrypt()`, `decrypt()` und `isNewFormat()`

Dazu kommen der Container-Schlüssel `loginThrottle`, das Cache-Verzeichnis
`data/cache/login-throttle` und die Meldungen: „A value for SECURITY_CIPHER_KEY must be set for
encryption." und „APP_TRUSTED_HEADERS = … is unknown."

**Zwei Werte, die gespeichert werden, sind mit geändert:**
- Die IDs der Rate-Limiter heissen `login-identifier-N` und `login-ip-N` statt
  `anmeldung-kennung-N`. Weil das Cache-Verzeichnis ebenfalls neu ist, mischt sich nichts.
- **Der Namensraum der Schlüsselableitung heisst `contentfly-field` statt `contentfly-feld`.** Mein
  erster Stand hatte ihn bewusst unverändert gelassen, weil jeder verschlüsselte Wert daran hängt.
  Der Auftraggeber hat entschieden, ihn doch zu ändern: Mit dem neuen Format ist noch nichts
  verschlüsselt, nirgends im Einsatz. Die Suche findet keinen fest hinterlegten Chiffretext im neuen
  Format. Der Kommentar hält fest, dass der Wert ab dem ersten Release fix ist.
  **Gegenprobe:** Ein Wert, verschlüsselt mit `contentfly-field`, lässt sich mit demselben Kontext
  entschlüsseln (`"secret"`). Mit dem alten Kontext liefert `decrypt()` `false`. Die Änderung wirkt
  also, und niemand liest versehentlich mit dem alten Schlüssel.

**Aufrufer:**
- `bootstrap.php` und `bootstrap-web.php`
- `AuthController` und `AuthControllerProvider`
- `StringType`, `TextareaType` und `ReencryptCommand`
- `Classes/Config.php` (Kommentar)
- drei umbenannte Testklassen, `AnmeldebremseApiTest`, `ReencryptCommandTest`,
  `ContainerSchluesselTest` und `IntegrationTestCase` (räumt jetzt `data/cache/login-throttle` auf)
- im dev-guide der Schlüssel `loginThrottle`

Die letzten vier Ausnahmen für `014-002` im Sprachwächter sind gestrichen.

Geprüft: volle Suite `OK (528 tests, 1699 assertions)` wie vorher, zweimal (vor und nach der
Änderung des Ableitungs-Kontexts). PHPStan `[OK] No errors`, Deprecation-Gate grün. Die Suche nach
deutschen Wörtern in den drei Klassen findet nichts.
