<!-- PURPOSE: TECH-STACK-Vorlage für "Individual" (kopiert von /new-project nach
     an_project/docs/tech-stack.md). /new-project füllt {{BACKEND}} und {{FRONTEND}}
     aus den Interview-Antworten. Freie Prosa, KEIN Work-Item-Frontmatter. -->
<!-- FUTURE: Neben dieser Datei kann später eine docker-compose.template.yml liegen,
     die /new-project für eine Test-Umgebung ins Projekt kopiert. Noch nicht gebaut. -->

# Tech-Stack: Individuell

<!-- /new-project ersetzt die beiden Blöcke unten aus den Antworten. Struktur für die
     spätere docker-compose-Automatisierung maschinenlesbar halten. -->

## Backend
PHP 8.5 (Zielplattform) · MySQL · Doctrine ORM · **Symfony 7.4 LTS** (Migrationsziel) — aktuell
noch Silex 2 mit Symfony-2/3-Komponenten. Symfony HttpFoundation wird bereits direkt in den
Controllern genutzt, die CLI-Commands basieren bereits auf Symfony Console.

**Fixiert auf Symfony 7.4 LTS** (Bugfixes bis Nov 2028, Security bis Nov 2029), nicht auf
Symfony 8.x. Symfony 8.0 hat denselben Funktionsumfang wie 7.4 — der Major entfernt nur
deprecated Code und hebt das PHP-Minimum auf 8.4. 7.4 verlangt nur PHP ≥ 8.2 und entkoppelt
damit die PHP- von der Kernel-Entscheidung — was zählt, solange Alt- und Neustand nebeneinander
laufen und solange Bestandsprojekte unterschiedliche PHP-Versionen mitbringen. Begründung und
Alternativen: `an_project/docs/architecture.md`, *Key decisions*.

**Pflicht dazu — deprecation-frei bauen:** Der spätere Sprung auf Symfony 8.4 LTS (erwartet
Nov 2027) ist nur dann ein reiner Constraint-Bump, wenn keine in 8.0 entfernten APIs benutzt
werden. Deprecation-Log und PHPStan gehören deshalb als Gate in die CI („0 Deprecations"), von
Tag 1 an — nicht erst vor dem Upgrade.

## Frontend
**Kein Frontend.** Contentfly ist reine Datenhaltung plus Core-Funktionen. Die mitgelieferte
PIM-CMS-Oberfläche (`lib/contentfly-ui`, Twig + Assets, 31 MB) wird ersatzlos gestrichen
(Epic `012-000-0000`); Twig fällt damit aus dem Abhängigkeitsbaum. Zugriff erfolgt über die API
und die Console.

## Build / Test / Run
<!-- Vom Team ergänzen: Install-, Build-, Test- und Run-Befehle je Stack.
     Das lokale Setup Schritt für Schritt steht in an_project/docs/runbook.md. -->

## Struktur & Konventionen

**Backend (Symfony) — IMMER in Bundles.** Wie bei Shopware 6 wird jeder fachliche
Bereich als eigenes Bundle organisiert — egal ob am Ende 1 oder 10 Bundles daraus
werden. So bleibt die Struktur über alle Projekte konstant.
- Ein Bundle pro fachlichem Bereich, Registrierung in `config/bundles.php`.
- PSR-4-Autoloading je Bundle; Controller, Services, Entities liegen im jeweiligen Bundle.
- Die konkrete Bundle-Liste dieses Projekts steht in `an_project/docs/dev-guide.md`.

**Frontend (Angular, mit/ohne Ionic) — IMMER `src/modules` + `src/shared`.**
- `src/modules/<feature>` — je Feature ein Modul.
- `src/shared` — wiederverwendbare Components, Services, Pipes.
- Die konkrete Modul-Map dieses Projekts steht in `an_project/docs/dev-guide.md`.
- In diesem Projekt aktuell nicht relevant (kein Frontend).

## Neue API-Route & API-Doku
<!-- Konstante Regel; die projektspezifische Ausprägung steht in an_project/docs/dev-guide.md. -->
- Eine neue Route entsteht als Controller-Action im zuständigen **Bundle**
  (Symfony `#[Route(...)]`).
- Die **API-Doku wird automatisch generiert**, nicht von Hand gepflegt: Symfony via
  `nelmio/api-doc-bundle` bzw. API Platform (OpenAPI/Swagger), Angular via `compodoc`.
- Der konkrete Regenerier-Befehl und der Ablageort der generierten Doku gehören ins
  `an_project/docs/runbook.md` und `an_project/docs/dev-guide.md`.
- Solange der Silex-Kernel läuft, entstehen Routen weiterhin über `custom/app.php`
  (`mount()`); die Bundle-Regel greift auf dem neuen Kernel.

## Verengung der Baseline
<!-- Fachliche Commit-Scopes (in an_project/docs/git.md) und bewusste Abweichungen. -->
