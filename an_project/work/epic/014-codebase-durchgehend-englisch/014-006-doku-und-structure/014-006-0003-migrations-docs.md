---
id: 014-006-0003
title: Migrations-Docs auf die englischen Namen
status: review
depends_on: [014-006-0002]
---

# Migrations-Docs auf die englischen Namen

## Context
`breaking-changes.md` (33 Treffer), `migration.md` und `pim-annotationen-migration.md` sagen einem
Bestandsprojekt, was es beim Umstieg ändern muss. Stehen dort `CONTENTFLY_PROJEKT`,
`Pfade::projekt()` oder `EntfalleneAttributfelderRector`, stellt ein Projekt auf Namen um, die es
nicht gibt.

Keiner der deutschen Namen war je in einem Release. Die Docs nennen deshalb nur die englischen
Namen; einen Abschnitt „umbenannt von …" gibt es nicht.

## Acceptance criteria
- [x] Jeder Code-Verweis in den drei Docs stimmt mit dem Code überein, Codebeispiele eingeschlossen.
- [x] Kein Abschnitt beschreibt eine Umbenennung von deutsch nach englisch.
- [x] Die Zahl der Register-Einträge und Abschnitte in `breaking-changes.md` ist unverändert.
  `MigrationGuideTest` und `RectorRuleTest`, die diese Docs lesen, sind grün.
- [x] Die Suche nach den alten Namen findet in den drei Dateien nichts.

## Verification
Suche mit der Liste der alten Namen. Skript, das Code-Verweise auflöst.
`phpunit tests/Unit/Migration` grün, volle Suite grün.

## Ergebnis

**Die Migrations-Docs nennen auf der „neu"-Seite nur Namen, die es gibt.** `breaking-changes.md`
hatte 45 Treffer, `migration.md` zwei, `pim-annotationen-migration.md` drei.

**Was ein Bestandsprojekt danach umstellt, stimmt jetzt:**
- **Einstiegspunkte und Pfade:** `CONTENTFLY_PROJECT_DIR`, `Paths::project()`, `data()`,
  `package()`, `Start::console()`.
- **Routing und Commands:** `RouteCollector` statt `$app['controllers_factory']`, `application()`
  statt `getSilexApplication()`.
- **Authentifizierung:** `BaseControllerProvider::authenticate()` statt `checkToken()` und
  `TokenSources` für die Token-Konstanten.
- **Login-Provider:** die sechs Schritte vom `LoginManager` zum Provider mit `LoginProvider`,
  `authenticate(Request): ?ExternalIdentity`, `SECURITY_PROVIDER_GROUPS` (`groups`, `admin`,
  `default`), `$app['loginProviders']->register()` und `ExampleProvider`.
- **Schema und Werkzeuge:** die Unique-Bedingung `uniq_user_external_identity`,
  `LdapProvider::fromConfig()`, `appcms:provider:sync` und `RemovedAttributeFieldsRector`.
- **Codebeispiel:** Die Variablen heissen englisch
  (`$identifierInExternalSystem`, `$groupsFromExternalSystem`).

**Zitate aus dem Code nachgezogen.** Das Beispiel `#[PIM\Config(label: 'Article')]` folgt
`rector.php`. Die Aliase der Prüfstein-Datei heissen `Other` und `Mapping`, wie seit
`014-005-0003` in `tests/Fixtures/RectorMigration/`.

**Prosa.** „Zugangstoken" als Begriff heisst jetzt „Login-Token". „Anmeldebremse" steht als
`LoginThrottle` da, weil die Klasse gemeint ist. „Tokenquellen ändert" ist umformuliert zu
„Quellen eines Tokens ändert".

**Kein Umbenennungs-Abschnitt.** Keine Stelle in den drei Docs beschreibt eine Umbenennung von
deutsch nach englisch. Eine Suche nach `014`, „umbenannt" und „hiess" findet nur Stellen, die damit
nichts zu tun haben. Bewusst deutsch bleibt `$app['schlüssel']` in der Liste „Was sich nicht
ändert": Das ist ein Platzhalter, kein Name, und `architecture.md` zitiert die Zeile wörtlich.

**Zwei Treffer der Meldungssuche** aus `014-006-0002` sind keine Zitate, sondern Prosa, die
zufällig gleich lautet: „einem Baum, der nicht mehr gilt" (Z. 597) und „Sperrt Benutzer, die ihr
Fremdsystem nicht mehr kennt" (Z. 1437). Sie bleiben.

**Nachweis.**
- Suche nach alten Namen über die drei Dateien: 0 Treffer.
- Alle neuen Namen lösen sich auf. Die übrigen Meldungen des Skripts sind die „alt"-Seite der
  Bruchstellen (`checkToken()`, `LoginManager`, `ConvertMapping` …) oder Aliase (`Other`,
  `Mapping`).
- 101 Abschnitte der Ebene `###` vor und nach dem Task.
- `phpunit tests/Unit/Migration`: `OK (22 tests, 411 assertions)`.
