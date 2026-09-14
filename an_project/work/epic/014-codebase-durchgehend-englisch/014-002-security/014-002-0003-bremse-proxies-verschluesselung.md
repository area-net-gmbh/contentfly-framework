---
id: 014-002-0003
title: Anmeldebremse, Proxies und Feldverschlüsselung auf Englisch
status: todo
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
- [ ] Die Klassen des Tasks sind vollständig englisch, mit Namen nach der Tabelle in Epic `014`, Methoden, Variablen, Meldungen und Kommentaren.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Testklassen, die nach einer umbenannten Klasse heissen, sind mit umbenannt; ihre Methodennamen und Kommentare folgen in `014-005`.
- [ ] Eine Suche nach jedem alten Namen findet keinen Code-Treffer mehr ausser in `an_project/` und `CHANGELOG.md`.
- [ ] Die Ausnahmen in `tests/Unit/EnglishOnlyTest.php`, die dieser Task auflöst, sind gestrichen.
- [ ] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan mit `--memory-limit=512M`,
`tools/ci/deprecations-pruefen.sh`, `grep -rnw` nach jedem alten Namen, Suche nach deutschen Wörtern
in den Dateien des Tasks, `sh tools/check-template-config.sh`.
