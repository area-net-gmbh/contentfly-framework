---
id: 014-002-0002
title: Anmeldeprovider auf Englisch
status: done
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

**Acht Klassen sind vollständig englisch.** Umbenannt sind:
- `Anmeldeprovider` → `LoginProvider` mit `authenticate()`
- `Fremdkennung` → `ExternalIdentity`; die Eigenschaften heissen `identifier`, `groups` und
  `attributes`
- `Bestandspruefung` → `UserExistenceCheck` mit `knowsIdentifier()`
- `Anbieterverzeichnis` → `LoginProviderRegistry` mit `register()`, `has()`, `get()` und `names()`
- `Benutzerbereitstellung` → `UserProvisioning` mit `findOrCreate()`
- `Gruppenabbildung` → `GroupMapping` mit `apply()`

`LdapProvider` und `OidcProvider` behalten ihren Namen und haben `fromConfig()`.

**Was ein Projekt sieht, heisst jetzt:**
- `$app['loginProviders']->register()`
- die Container-Schlüssel `userProvisioning` und `groupMapping`
- die Config-Keys `SECURITY_PROVIDER_GROUPS` (Einträge `groups`, `admin`, `default`),
  `SECURITY_LDAP_GROUP_ATTRIBUTE`, `SECURITY_OIDC_IDENTIFIER_CLAIM` und `SECURITY_OIDC_GROUPS_CLAIM`
- im LDAP-Filter der Platzhalter `{identifier}` statt `{kennung}`, Vorgabe
  `(sAMAccountName={identifier})`

Die interne Einstellungs-Liste der Provider heisst `group_attribute`, `endpoint`,
`identifier_claim` und `groups_claim`. Die Kommentare im Config-Block sind übersetzt, das Beispiel
nennt englische Gruppennamen.

**Aufrufer:**
- `AuthController`, `ProviderAbgleichCommand`, `bootstrap.php` und `Entity/User.php` (Kommentar)
- `custom/app.php`; `BeispielProvider` folgt dem Interface mit `authenticate()` und
  `knowsIdentifier()`
- drei umbenannte Testklassen, `LdapProviderTest`, `OidcProviderTest`, drei Integrationstests
- `LoginProviderAufloesungTest`
- im dev-guide die drei Schlüssel, gegen die `ContainerSchluesselTest` abgleicht

Im Sprachwächter sind sechs Ausnahmen gestrichen.

**Eine Lücke im Ersetzen, von der Unit-Suite gefunden:** Drei Aufrufe in `GroupMappingTest` waren
über zwei Zeilen umgebrochen (`$this->abbildung()` / `->anwenden(`). Das Muster erfasste nur die
einzeilige Form, und die Suite meldete `Call to undefined method GroupMapping::anwenden()`.
Nachgezogen, und die Nachsuche nach umgebrochenen Aufrufen alter Methoden findet nichts mehr.

Geprüft: volle Suite `OK (528 tests, 1699 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün, `console list` läuft. Die Suche nach deutschen Wörtern in den acht Klassen
findet nichts.
