<!-- PURPOSE: Laufende Historie des Projekts. Jeder verändernde Command und jede nennenswerte Änderung hängt hier einen datierten Eintrag an, neuestes Datum zuoberst — damit du und Claude sehen, was passiert ist. Ausnahmen: /board ist read-only, und /commit schreibt Git-Historie statt Projektdateien. -->

# Changelog

## 2026-09-04
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
