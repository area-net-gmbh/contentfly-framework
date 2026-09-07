<!-- PURPOSE: Laufende Historie des Projekts. Jeder verändernde Command und jede nennenswerte Änderung hängt hier einen datierten Eintrag an, neuestes Datum zuoberst — damit du und Claude sehen, was passiert ist. Ausnahmen: /board ist read-only, und /commit schreibt Git-Historie statt Projektdateien. -->

# Changelog

## 2026-09-07
- 012-006-0000 → in-progress
- 012-006-0001 → in-progress
- 012-006-0001 → review: `TypeManager::getCustomTypes/getSystemTypes/getPluginTypes` samt `$mode`-Filter und den Konstanten `CUSTOM`/`PLUGINS`/`SYSTEM` entfernt sowie `Type::renderJSON()` — alle vier ohne Verbraucher, der `UiController` war ihr Nutzer. Typ-Klassen referenzieren keine Templates oder Assets. Verifiziert: API-Schema vor und nach dem Umbau identisch, 26 Tests grün. Stehen geblieben: die nie gelesenen Schema-Keys `multipe`/`multiple` — ihr Entfernen würde das Schema ändern, was diese Story ausschließt
- 012-006-0002 → in-progress
- 012-006-0002 → review: Die UI-Schnittstelle lag nicht im `PluginManager` (der ist bereits sauber), sondern in `Classes/Plugin.php`: `getFrontendPath()`, `useFrontend()` und `normalizePath()` entfernt — alle ohne Aufrufer. Erweiterbarkeit für Entities, Types, Services und Commands unverändert, mit einem Wegwerf-Plugin nachgewiesen (Command ausführbar, Entity im Doctrine-Mapping und im API-Schema mit ausgewerteten Annotationen). Nebenbefund: `PluginManager::getPlugin()` referenziert eine nicht existierende Variable `$key` — echter Fehler, kein UI-Belang, nicht angefasst
- an_project/docs/pim-annotationen-migration.md updated: entfallene Plugin-Schnittstelle als Breaking Change für Epic 007 ergänzt
- 012-005-0000 → done (merged into master)
- 012-005-0001 … 012-005-0004 → done: mit der Story geschlossen
- 012-005-0000 → in-progress
- 012-005-0001 → in-progress
- 012-005-0001 → review: 7 der 11 Widget-Annotationen gelöscht (`Rte`, `Textarea`, `Datetime`, `Time`, `Password`, `MatrixChooser`, `EntitySelector`) samt `RteType`, `PasswordType`, `EntitySelectorType`. `Checkbox`, `Radio`, `Select` und `Virtualjoin` bleiben — sie wählen Type-Klassen aus, die Collections auflösen, Leseberechtigungen prüfen, OptionGroups anlegen und den Wertebereich tragen; aus ihnen sind nur die Darstellungsfelder entfernt. Verifiziert: Schema-Erzeugung gegen die Dev-Datenbank (HTTP 200, 14 Entities), 26 Tests grün
- 012-005-0002 → in-progress
- 012-005-0002 → review: `@PIM\Config` von 24 auf 10 Felder reduziert. `readonly` und `filter` einzeln geprüft — beide reine UI-Hinweise ohne Leser, `readonly` ist kein API-Schreibschutz. **`labelProperty` bleibt entgegen der ursprünglichen Streichliste**: es wird als `modelLabel` ins `Log` persistiert und steuert den `partial`-Select verjointer Objekte; Story und Kriterium entsprechend korrigiert
- an_project/docs/pim-annotationen-migration.md created: vollständige Liste der entfallenen Annotationen, Config-Felder und Konstanten — Grundlage der Rector-Regel aus Epic 007
- 012-005-0003 → in-progress
- 012-005-0003 → review: `Type.php`, `Api.php`, `OnejoinType`, `FileType`, `MultifileType` und `Serializable` auf die verbliebenen Felder gezogen; Tab-Mechanik, Listenaufbau und der Schema-Schlüssel `list` entfallen. **`showInList` hatte zwei Datenleser** — `Api::getTree2()` (Route `/api/tree2`) und die Beschränkung verschachtelter Objekte in `Serializable::toValueObject()`; beide liefern jetzt alle Eigenschaften, für Clients additiv. Nebenbefund behoben: `getTree2()` quotete Spaltennamen nie, mit `groups` traf das ein in MySQL 8 reserviertes Wort. Verglichen gegen einen `master`-Worktree auf derselben Datenbank: nur UI-Schlüssel entfallen, kein neuer Schlüssel, alle datenrelevanten Werte gleich
- 012-005-0004 → in-progress
- 012-005-0004 → review: 195 Felder aus 96 `@PIM\Config`-Annotationen in 19 Entities entfernt, 20 datenrelevante bleiben; ein Fundort lag in `custom/Traits/User.php` außerhalb der Entity-Verzeichnisse. `FRONTEND_SHOW_ID_IN_LIST`, `FRONTEND_SHOW_OWNER_IN_LIST` und `FRONTEND_TAB_GENERAL_NAME` samt der abgeleiteten `APP_CMS_*`-Konstanten entfallen. Verifiziert: `orm:schema-tool:update --dump-sql` liefert gegen dieselbe Datenbank byte-identische Ausgabe wie ein `master`-Worktree — das Datenbankschema ist unverändert
- 012-005-0000 → review: vier Tasks auf einem Branch. Von 15 Annotationsklassen bleiben 8, von 24 `Config`-Feldern 10, von 96 Entity-Annotationen 20. Zwei Korrekturen am Umfang der Story: `labelProperty` bleibt (wird ins `Log` persistiert und steuert den Join-Payload), und `showInList` hatte mit `/api/tree2` und den verschachtelten Objekten zwei Datenleser — beide liefern jetzt alle Felder, additiv. Verifiziert gegen einen `master`-Worktree auf derselben Datenbank: Datenbankschema byte-identisch, im API-Schema entfallen ausschließlich UI-Schlüssel, kein neuer hinzugekommen, alle datenrelevanten Werte gleich; 26 Tests grün

## 2026-09-04
- 012-004-0000 → done (merged into master)
- 012-004-0001 … 012-004-0003 → done: mit der Story geschlossen
- 012-004-0000 → in-progress
- 012-004-0001 → in-progress
- 012-004-0001 → review: fünf Session-Fundstellen erfasst; kein API-Zustand liegt in der Session. Nebenbefund: `Auth::login()`, `logout()`, `getLoginProvider()` und `getToken()/setToken()` haben keinen einzigen Aufrufer
- 012-004-0002 → review: `SessionServiceProvider`, `Auth::init()` und die drei `session.*`-ini-Settings entfernt; `Auth` auf `getUser()`/`setUser()` reduziert. Nachgewiesen: kein Request setzt mehr ein PHPSESSID-Cookie, Anmeldung und Token-Weg unverändert
- 012-004-0003 → review: 10 Tests für die Token-Authentifizierung — Anmeldung, falsches Passwort, unbekannter Benutzer, Zugriff ohne/mit falschem/mit gültigem Token, Abmelden entwertet den Token, plus Regressionsschutz gegen eine zurückkehrende Session
- 000-000-0006 updated: Zugriff ohne Token endet ebenfalls mit 500 statt 401 — dieselbe Ursache wie beim Datei-404
- 012-004-0000 → review: drei Tasks umgesetzt, 26 Integrationstests grün
- 012-003-0000 → done (merged into master)
- 012-003-0001 … 012-003-0003 → done: mit der Story geschlossen
- 000-000-0006 task created: "Fehlerantworten und WEB_ROOT — 404 wird zu 500, Redirects sind umgebungsabhängig" (bei den Charakterisierungstests aufgefallen)
- 012-003-0003 → review: 16 Tests für die Datei-API — Upload (inkl. des zufällig funktionierenden `$_FILES`-Pfads), Speicherung byte-gleich, Auslieferung als Redirect ohne Token, unbekannte ID, Überschreiben samt Gleichnamen-Vorbedingung; `tests/router.php` ergänzt, weil der eingebaute Server sonst keine Dateien ausliefert
- 012-003-0000 → review: drei Tasks umgesetzt; Ergebnis der Story ist, dass am FileController nichts zu schneiden war — der Wert liegt in den Tests
- 012-003-0000 → in-progress
- 012-003-0001 → in-progress
- 012-003-0001 → review: alle drei Actions des FileControllers als API eingestuft — es gibt keine Admin-Dateiverwaltung darin; die Dateiansicht der Oberfläche lag im gelöschten Angular-Frontend und ging über die generischen `/api`-Endpunkte
- 012-003-0002 → review: ohne Codeänderung geschlossen — die Story ging von einer Vermischung aus, die es nicht gibt
- 000-000-0005 → done (merged into master)
- 000-000-0005 → in-progress
- 000-000-0005 → review: `tests/bootstrap.php` (beide Autoloader, ROOT_DIR, die Konstanten aus den Entity-Annotationen), `phpunit.xml.dist` auf dieses Repo ausgerichtet, Suiten `unit`/`integration` getrennt, fest verdrahtete Coverage-Berichte entfernt (sie ließen die Suite ohne Xdebug mit Exit-Code 1 enden), sechs echte Tests für den `ApiDateTimeFormatter`, `tests/README.md` und Runbook-Abschnitt. Verifiziert: 6 Tests grün mit Exit-Code 0, ein absichtlich gebrochener Assert lässt die Suite fehlschlagen
- 000-000-0005 task created: "Test-Grundgerüst herstellen" (standalone; ohne `tests/` läuft kein Test — blockiert 012-003-0003, 012-004-0003 und Epic 008)
- 012-002-0000 → done (merged into master)
- 012-002-0001 … 012-002-0003 → done: mit der Story geschlossen
- 000-000-0002 → done (merged into master)
- 000-000-0004 → done (merged into master)
- 000-000-0002 → review: `custom/config.php` von der Kundendatei auf die Vorlage zurückgeführt — `$SET_*`-Platzhalter (inkl. neuem `$SET_DB_PORT`), generische Kommentare, kein hinterlegter `SECURITY_CIPHER_KEY` mehr
- 000-000-0004 task created + → review: `DB_PORT` wurde von der ORM-Verbindung ignoriert (immer 3306) — durchgereicht in `bootstrap.php`, `--db-port` im Install-Command, Platzhalter in der Vorlage
- 012-002-0002 → review: Happy Path end-to-end verifiziert — Installation gegen die Dev-Datenbank auf 3307 legt 17 Tabellen an, Admin mit GUID, zwei Thumbnail-Größen; Anmeldung über `/auth/login` liefert ein Token. Dabei behoben: fehlende `ContentflyQuoteStrategy` in der eigenen Doctrine-Registrierung — ohne sie scheitert der INSERT an `groups`, seit MySQL 8.0.2 ein reserviertes Wort
- 012-002-0003 → review: `InstallController`, `install.twig`, `lib/contentfly-ui/` und die Twig-Registrierung entfernt; `BaseController` kommt ohne Twig und ohne ORM aus; der Redirect auf die Installer-Maske wurde durch eine 503-Antwort mit Handlungsanweisung ersetzt; toter Schlüssel `APP_INSTALLER_URL` entfernt
- 012-002-0000 → review: drei Tasks umgesetzt, Installation und Anmeldung gegen eine echte Datenbank nachgewiesen
- 000-000-0003 → done (merged into master)
- 000-000-0003 → in-progress
- 000-000-0003 → review: `docker-compose.yml` mit MySQL 8.0 auf Port 3307 (utf8mb3/utf8mb3_unicode_ci, benanntes Volume, Healthcheck) ins Repo; die vier Docker-Zeilen aus der `.gitignore` entfernt; `data/{files,cache,temp,import}` mit `.gitkeep` versioniert; Runbook mit Hochfahren, Verbinden, Zurücksetzen und Herunterfahren. Verifiziert: Container healthy nach ~9s, PHP verbindet sich, `down -v` + `up` liefert eine leere Datenbank, der fremde Container auf 3306 bleibt unberührt
- 000-000-0003 task created: "Lokale Entwicklungsumgebung mit MySQL bereitstellen" (standalone; hebt die Blockade für 012-002, 012-003, 012-004 und Epic 008 auf)
- 013-000-0000 epic created: "Authentifizierung — stateful und stateless nebeneinander"
- 013-001-0000 story created: "Auth-Härtung — Passwörter, Master-Passwort, Rate-Limiting, Token-Speicherung" (vorgezogen, ohne Abhängigkeiten)
- 013-002-0000 story created: "access_token-Authenticator mit verzweigendem TokenHandler"
- 013-003-0000 story created: "JWT ausstellen und widerrufen"
- 013-004-0000 story created: "Nachfolger des LoginManagers"
- 013-005-0000 story created: "Active Directory und OIDC anbinden"
- an_project/docs/technical.md updated: Auth-Review vom 2026-09-04 festgehalten — Ist-Zustand, sechs Sicherheitsbefunde, drei funktionale Defekte
- 012-002-0000 → in-progress
- 012-002-0001 → in-progress
- 012-002-0002 → in-progress
- 012-002-0002 fixed: `--dry-run` rief `chmod()` auf und veränderte damit Dateirechte, obwohl es „nichts geschrieben" meldet — die Rechte werden jetzt nur im Schreibpfad gesetzt
- 000-000-0002 task created: "custom/config.php auf die Vorlage zurückführen — Platzhalter statt Kundendaten" (standalone; blockiert die End-to-End-Verifikation von 012-002-0002)
- bin/console.php fixed: Doctrine-Helper und -Commands an `is_installed` gebunden — die Konsole war auf einem frischen Checkout nicht startbar, `appcms:install` damit unerreichbar
- 012-002-0001 → review: die elf Schritte des InstallControllers erfasst und im Task dokumentiert; Erkenntnis: Schritt 10 (Basisdaten) existiert bereits als `appcms:setup`, neu sind die Schritte 0–9 samt Schreiben der `config.php`
- 000-000-0001 → done (merged into master)
- 000-000-0001 → in-progress
- 000-000-0001 → review: `custom/app.php` durch eine schlanke, bootfähige Vorlage ersetzt (1358 → 76 Zeilen); fehlenden `ApiDateTimeFormatter` ergänzt; Muster der Kundendatei (Middleware-Reihenfolge, Session-Write-Close, Trusted Proxies, Security-Header, CORS-Allowlist, Pro-Tenant-JWT-Secret) nach `an_project/docs/technical.md` übernommen; verifiziert mit `php bin/console.php list` und einem HTTP-Aufruf auf `/api/v1/example/bootstrap` (HTTP 200)
- 000-000-0001 task created: "custom/app.php bootfähig machen — Vorlage referenziert fehlende Klassen" (standalone; bei der Umsetzung von 012-001 aufgefallen, vorbestehender Defekt)
- 012-001-0000 → done (merged into master)
- 012-001-0001 … 012-001-0004 → done: mit der Story geschlossen, sie lagen als Commits auf deren Branch
- 012-001-0000 → in-progress
- 012-001-0001 → in-progress
- 012-001-0002 → in-progress
- 012-001-0003 → in-progress
- 012-001-0004 → in-progress
- 012-001-0001 → review: UiController, Service-Definition `ui.controller`, seine vier Routen und der tote Konfigurationsschlüssel `APP_DEFAULT_CONTROLLER` entfernt; per grep und `php -l` verifiziert
- 012-001-0002 → review: UIManager gelöscht, Factory `$app['uiManager']` entfernt, die sechs UI-Registrierungsmethoden aus `Plugin.php` entfernt, `uiblocks` aus `/api/config` und `getExtendedSchema()` entfernt; per grep und `php -l` verifiziert
- 012-001-0003 → review: ExportController samt Provider, `/export`-Mount und den Konfigurationsschlüsseln `APP_EXPORT_METHODS`/`APP_EXPORT_CONTROLLER` entfernt; `ellumilel/php-excel-writer` hat keinen Verbraucher mehr
- 012-001-0004 → review: `lib/contentfly-ui/` von 31 MB auf 32 KB reduziert (nur `install.twig` bleibt bis 012-002), Admin-Partials aus `custom/Views/` entfernt, `install.twig` von gelöschten Assets entkoppelt
- 012-001-0000 → review: vier Tasks umgesetzt; Verifikation statisch (grep + `php -l` über den gesamten Framework-Code), da das Repo aus einem vorbestehenden Grund nicht bootet — `custom/app.php:9` verlangt `Custom\Classes\Service\Bootstrap\SecretsCheck`, die es in der ausgedünnten Vorlage nicht gibt
- 012-000-0000 decided: UI-Anteile der `@PIM\Config`-Annotationen werden bei Bestandsprojekten **hart entfernt** — keine Duldungsphase; Epic 007 liefert dazu eine Rector-Regel
- 012-001-0001 … 012-006-0003 tasks created: 20 Tasks für die sechs Stories von Epic 012
- 012-001-0000 / 012-002-0000 updated: Twig-Entfernung nach 012-002 verschoben — der InstallController ist bis dahin der letzte Verbraucher und der einzige Installationsweg
- 012-001-0000 story created: "Oberfläche und UI-Controller löschen" (Epic 012)
- 012-002-0000 story created: "Installer als Console-Command" (Epic 012)
- 012-003-0000 story created: "FileController schneiden — API behalten, Datei-UI entfernen" (Epic 012)
- 012-004-0000 story created: "Sessionbasierte Admin-Auth entfernen" (Epic 012)
- 012-005-0000 story created: "@PIM-Annotationen entrümpeln" (Epic 012)
- 012-006-0000 story created: "TypeManager und PluginManager von UI-Belangen befreien" (Epic 012)
- 012-000-0000 epic created: "PIM-CMS-Oberfläche ersatzlos entfernen" — Produktentscheidung: keine Admin-/PIM-UI mehr, reine Datenhaltung + Core-Funktionen
- 006/008/009/010 updated: auf die UI-Streichung ausgerichtet — Excel-Writer und Twig entfallen ersatzlos, Testnetz deckt nur überlebende Controller ab, Kernel-Port ohne Twig und Session-Auth, Annotations-Umstellung erst nach der Entrümpelung
- 007-000-0000 updated: Wegfall der Oberfläche und der UI-Annotationen als größter Migrationsbrocken aufgenommen
- an_project/project-description.md updated: Produktentscheidung „keine Oberfläche mehr" festgehalten
- an_project/docs/tech-stack.md updated: Frontend-Abschnitt auf „kein Frontend, Twig entfällt" gesetzt
- 001-000-0000 … 005-000-0000 deleted: Epics P0–P4 verworfen — sie beschrieben die Migration eines Kundenprojekts, nicht das Framework-Update dieses Repos. IDs bleiben als Lücke.
- 008-000-0000 epic created: "Testnetz für das Framework vor dem Umbau"
- 009-000-0000 epic created: "Silex durch Symfony 7.4 ersetzen"
- 010-000-0000 epic created: "Doctrine und Entity-Layer modernisieren"
- 011-000-0000 epic created: "Release der neuen Framework-Version"
- 007-000-0000 updated: depends_on auf [009, 010] umgestellt
- an_project/project-description.md updated: Description und Goal auf das Framework-Update umgeschrieben; offene Produktentscheidung zur PIM-CMS-Oberfläche festgehalten
- an_project/docs/tech-stack.md updated: Frontend-Abschnitt korrigiert — Admin-UI ist Produktbestandteil, kein Wegfall
- 007-000-0000 epic created: "Migrationspfad für Bestandsprojekte"
- an_project/project-description.md updated: Scope ergänzt — Repo dient nur dem Framework-Update, kein Produktivbetrieb; einzige Randbedingung ist die Migrierbarkeit von Bestandsprojekten
- an_project/docs/deployment.md updated: auf den neuen Scope umgeschrieben — kein Produktiv-Rollout aus diesem Repo, `vendor/` kann sofort aus Git
- 006-000-0000 updated: Rücksicht auf Produktivbetrieb entfernt, Bestandsprojekt-Sicht ergänzt
- 006-000-0000 epic created: "Composer-Wiederherstellung und Dependency-Konsolidierung"
- 002-000-0000 updated: depends_on um 006-000-0000 ergänzt; Auflösung des Doctrine-Dev-Pins nach 006 verschoben
- an_project/docs/technical.md updated: Ist-Zustand dokumentiert — `custom/` als Vorlage, `vendor/`-in-Git als bewusste PHP-8-Notlösung, doppelte Autoloader, PHP-8.5-Blocker im Alt-Baum
- an_project/docs/deployment.md updated: Entscheidung „Composer als Quelle, Vendor im Deployment-Artefakt" festgehalten
- 001-000-0000 epic created: "P0 — Safety Net (Charakterisierungstests der API)"
- 002-000-0000 epic created: "P1 — Kernel-Scaffold (Symfony 7.4 LTS + pim-compat)"
- 003-000-0000 epic created: "P2 — Port von Routen, Middleware und Controllern"
- 004-000-0000 epic created: "P3 — Silex und PIM-CMS entfernen, C-4-Daten migrieren"
- 005-000-0000 epic created: "P4 — Cutover und Härtung"
- an_project/docs/architecture.md updated: Entscheidung „Ziel-Kernel Symfony 7.4 LTS" unter *Key decisions* festgehalten (Alternative Symfony 8.1 verworfen), Projektname im Heading gesetzt
- an_project/docs/tech-stack.md updated: Ziel-Stack auf Symfony 7.4 LTS fixiert, CI-Gate „0 Deprecations" als Pflicht ergänzt
- an_project/project-description.md created: "Contentfly Framework"
- an_project/docs/tech-stack.md created: Individual (PHP 8.5 · MySQL · Doctrine ORM · Symfony 7 als Migrationsziel, kein Frontend)
- an_project/docs/runbook.md created: Individual
- CLAUDE.md updated: Import `@an_project/docs/tech-stack.md` ergänzt

<!--
Format — neuestes Datum oben. Ein Bullet pro nennenswerter Änderung. Die Aktions-Marker
(„epic created", „task created", „→ review", „→ done", „updated") bleiben englisch —
der Detailtext dahinter ist deutsch:

## YYYY-MM-DD
- <ID oder Datei> <action>: <Detail>

Beispiele:
- 001-000-0000 epic created: "Authentifizierung"
- 001-001-0001 task created: "Login-Formular"
- 001-001-0001 → review: Login-Formular umgesetzt, per `npm test` verifiziert
- an_project/docs/git.md updated: Commit-Message-Konvention ergänzt
-->
