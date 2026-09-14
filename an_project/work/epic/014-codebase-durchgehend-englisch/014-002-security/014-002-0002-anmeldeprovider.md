---
id: 014-002-0002
title: Anmeldeprovider auf Englisch
status: todo
depends_on: [014-002-0001]
---

# Anmeldeprovider auf Englisch

## Context
Die Anmeldung über ein Fremdsystem hat die meisten deutschen Namen, die ein Projekt sieht:
`$app['anmeldeanbieter']->eintragen()` steht in jeder `custom/app.php`.

Umfang nach der Tabelle in Epic `014`:
- `Anmeldeprovider` → `LoginProvider` mit `authenticate()` statt `pruefen()`
- `Fremdkennung` → `ExternalIdentity`
- `Bestandspruefung` → `UserExistenceCheck` mit `knowsIdentifier()`
- `Anbieterverzeichnis` → `LoginProviderRegistry` mit `register()`, `has()`, `get()` und `names()`;
  Container-Schlüssel `loginProviders`
- `Benutzerbereitstellung` → `UserProvisioning` mit `findOrCreate()`; Schlüssel `userProvisioning`
- `Gruppenabbildung` → `GroupMapping` mit `apply()`; Schlüssel `groupMapping`
- `LdapProvider` und `OidcProvider`: `fromConfig()` statt `ausKonfiguration()`, dazu alle
  Methoden, Meldungen und Kommentare
- Config-Keys `SECURITY_PROVIDER_GROUPS` (Einträge `groups`, `admin`, `default`),
  `SECURITY_LDAP_GROUP_ATTRIBUTE`, `SECURITY_OIDC_IDENTIFIER_CLAIM` und
  `SECURITY_OIDC_GROUPS_CLAIM`, samt Kommentaren in `Classes/Config.php`

**`BeispielProvider` folgt dem Interface.** `pruefen()` wird `authenticate()` und
`kenntKennung()` wird `knowsIdentifier()`, sonst erfüllt die Vorlage das Interface nicht mehr.
Klassen- und Namensraumname der Vorlage bleiben bis `014-004`. `custom/app.php` ruft
`$app['loginProviders']->register()`.

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
