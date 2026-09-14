---
id: 014-000-0000
title: Codebase durchgehend Englisch
status: todo
depends_on: []
---

# Codebase durchgehend Englisch

## Goal
**Im Code steht kein deutsches Wort mehr.** Das gilt für Klassen, Methoden, Variablen,
Container-Schlüssel, Config-Keys, Umgebungsvariablen, Command-Namen, Exception-, Fehler- und
Konsolenmeldungen, Kommentare und Testmethoden in `lib/`, `custom/`, `bin/`, `tests/`,
`index.php` und `phpunit.xml.dist`.

**Warum:** Das alte Contentfly ist durchgehend englisch. Die deutschen Namen sind erst in der
Arbeit an Version 2 entstanden (`Pfade`, `Anmeldebremse`, `Start::konsole()`,
`$app['anmeldeanbieter']` …). Neben bestehenden englischen Klassen wie `Api`, `Auth`, `Helper`
oder `Messages` ergibt das eine Mischung, die keinen Sinn hat. So entschieden vom Auftraggeber am
2026-09-14.

**Jetzt und nicht später:** Keiner dieser Namen war je in einem Release. Heute kostet das
Umbenennen keinen Bruch für Bestandsprojekte, nach dem Release wäre es einer. Die Codebase geht
außerdem an die IT-Security (`000-000-0033`). **Übergeben wird erst nach diesem Epic**, sonst
beziehen sich Befunde auf Namen, die es danach nicht mehr gibt.

Gemessen am 2026-09-14: 234 PHP-Dateien, rund 45 deutsch benannte Klassen, rund 150 deutsch
benannte Methoden, 524 Testmethoden mit überwiegend deutschen Namen und Kommentare in nahezu
jeder Datei.

### Verbindliche Namen für alles, was ein Projekt sieht

Diese Tabelle gilt über alle Stories. Eine Story, die davon abweichen will, ändert zuerst diese
Tabelle, damit zwei Stories nie zwei Namen für dasselbe vergeben.

| Bisher | Neu |
|---|---|
| `Kernel\Pfade` · `projekt()` `paket()` `daten()` `setzen()` `istGesetzt()` `zuruecksetzen()` `entitiesDesFrameworks()` `entitiesDesProjekts()` | `Kernel\Paths` · `project()` `package()` `data()` `set()` `isSet()` `reset()` `frameworkEntities()` `projectEntities()` |
| `Start::konsole()` | `Start::console()` |
| Konstante `CONTENTFLY_PROJEKT` | `CONTENTFLY_PROJECT_DIR` |
| `Kernel\Command::anwendung()`, `Console::anwendung()` `projektverzeichnis()` | `application()`, `projectDir()` |
| `Kernel\Routing\Routensammlung` · `sammlung()` | `RouteCollector` · `collection()` |
| `Kernel\Routing\Routeneintrag` | `RouteEntry` |
| `Kernel\Routing\AbsicherungListener` | `RouteSecurityListener` |
| `Application::routen()` | `routes()` |
| `Metadaten\Metadatenleser` · `klasse()` `eigenschaft()` | `Metadata\MetadataReader` · `forClass()` `forProperty()` |
| `Security\Anmeldeprovider` · `pruefen()` | `Security\LoginProvider` · `authenticate()` |
| `Security\Fremdkennung` | `Security\ExternalIdentity` |
| `Security\Bestandspruefung` · `kenntKennung()` | `Security\UserExistenceCheck` · `knowsIdentifier()` |
| `Security\Anbieterverzeichnis` · `eintragen()` `hat()` `holen()` `namen()` | `Security\LoginProviderRegistry` · `register()` `has()` `get()` `names()` |
| `Security\Anmeldebremse` · `wartezeit()` `fehlversuch()` `entsperren()` | `Security\LoginThrottle` · `retryAfter()` `recordFailure()` `reset()` |
| `Security\Anmeldetreiber` · `benutzer()` | `Security\TokenAuthenticator` · `user()` |
| `Security\Benutzerlader` | `Security\UserLoader` |
| `Security\Benutzerbereitstellung` · `findenOderAnlegen()` | `Security\UserProvisioning` · `findOrCreate()` |
| `Security\Gruppenabbildung` · `anwenden()` | `Security\GroupMapping` · `apply()` |
| `Security\Tokenhandler` | `Security\TokenHandler` |
| `Security\Tokenquellen` · `kette()` `quellen()` | `Security\TokenSources` · `chain()` `sources()` |
| `Security\RohkopfExtractor` · `RumpfExtractor` | `RawHeaderExtractor` · `BodyExtractor` |
| `Security\Zugangstoken` · `ausstellen()` | `Security\JwtAccessToken` · `issue()` |
| `Security\VertrauteProxies` · `anwenden()` | `Security\TrustedProxies` · `apply()` |
| `Security\Feldverschluesselung` · `verschluesseln()` `entschluesseln()` | `Security\FieldEncryption` · `encrypt()` `decrypt()` |
| `LdapProvider::ausKonfiguration()`, `OidcProvider::ausKonfiguration()` | `fromConfig()` |
| Container `anmeldeanbieter` · `loginbremse` · `anmeldetreiber` · `benutzerbereitstellung` · `gruppenabbildung` · `tokenhandler` | `loginProviders` · `loginThrottle` · `tokenAuthenticator` · `userProvisioning` · `groupMapping` · `tokenHandler` |
| Config `SECURITY_PROVIDER_GRUPPEN` (Einträge `gruppen`, `admin`, `vorgabe`) | `SECURITY_PROVIDER_GROUPS` (Einträge `groups`, `admin`, `default`) |
| Config `SECURITY_LDAP_GRUPPEN_ATTRIBUT` · `SECURITY_OIDC_KENNUNG_CLAIM` · `SECURITY_OIDC_GRUPPEN_CLAIM` | `SECURITY_LDAP_GROUP_ATTRIBUTE` · `SECURITY_OIDC_IDENTIFIER_CLAIM` · `SECURITY_OIDC_GROUPS_CLAIM` |
| Command `appcms:provider:abgleich` · `ProviderAbgleichCommand` | `appcms:provider:sync` · `ProviderSyncCommand` |
| `Migration\EntfalleneAttributfelderRector` | `Migration\RemovedAttributeFieldsRector` |
| Cache-Verzeichnis `data/cache/loginbremse` | `data/cache/login-throttle` |
| Vorlage `Custom\Classes\Anmeldung\BeispielProvider`, Providername `beispiel` | `Custom\Classes\Authentication\ExampleProvider`, `example` |
| Umgebungsvariable `CONTENTFLY_BEISPIEL_PROVIDER` | `CONTENTFLY_EXAMPLE_PROVIDER` |
| Test-Umgebungsvariable `CONTENTFLY_TEST_PROJEKT` | `CONTENTFLY_TEST_PROJECT_DIR` |

Für alles, was nicht in der Tabelle steht (private Methoden, Variablen, Testhelfer), gilt: ein
treffender englischer Name im Stil des umgebenden Codes, keine wörtliche Übersetzung um jeden
Preis.

### Regeln für jede Story

- **Kein Alias und kein Übergangsname.** Die alten Namen verschwinden. Es gibt nichts, das sie
  schützen müssten.
- **Verhalten bleibt gleich.** Geändert werden Namen, Meldungstexte und Kommentare. Eine
  Zusicherung, die sich dabei ändern müsste, ist ein Befund und bekommt einen eigenen Task.
- **Kommentare werden übersetzt, nicht gekürzt.** Verweise auf Work-Item-IDs und auf
  `an_project/docs` dürfen bleiben.
- **Jede Story endet mit der vollen Suite, PHPStan und dem Deprecation-Gate grün**, dazu
  `php bin/console.php list` und `custom/config.php` wiederhergestellt.
- **Am Ende der Story findet eine Suche in ihrem Bereich kein deutsches Wort mehr.** Geprüft
  werden Umlaute, ß und eine Liste typischer deutscher Wörter in Bezeichnern, Strings und
  Kommentaren. Die Suche wird gegen einen absichtlich deutschen Rest gegengeprüft.

### Nicht Teil des Epics

- **Prosa in `an_project/`, `CHANGELOG.md`, Work-Items und Commit-Bodies bleibt deutsch.** Das
  ist die Sprachregel des Frameworks. Nachgezogen werden dort nur die Namen.
- **`tools/` und `.gitlab-ci.yml`** gehören nicht zur Codebase-Übergabe. Nachgezogen werden
  dort die Verweise auf umbenannte Namen (etwa `CONTENTFLY_EXAMPLE_PROVIDER`), damit die
  Pipeline weiterläuft. Skriptnamen und Kommentare dort bleiben.
- Datenbankspalten und Tabellennamen sind bereits englisch und bleiben unverändert.

## Stories
- [ ] 014-001-0000 — Kernel, Routing, Metadaten und Einstiegspunkte auf Englisch
- [ ] 014-002-0000 — Security-Schicht auf Englisch
- [ ] 014-003-0000 — Restliches Framework-Paket auf Englisch
- [ ] 014-004-0000 — Vorlage custom/ auf Englisch
- [ ] 014-005-0000 — Testsuite auf Englisch
- [ ] 014-006-0000 — Doku und STRUCTURE.md nachziehen
