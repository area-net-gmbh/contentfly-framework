<!-- PURPOSE: TECH-STACK-Vorlage für "Individual" (kopiert von /new-project nach
     an_project/docs/tech-stack.md). /new-project füllt {{BACKEND}} und {{FRONTEND}}
     aus den Interview-Antworten. Freie Prosa, KEIN Work-Item-Frontmatter. -->
<!-- FUTURE: Neben dieser Datei kann später eine docker-compose.template.yml liegen,
     die /new-project für eine Test-Umgebung ins Projekt kopiert. Noch nicht gebaut. -->

# Tech-Stack: Individuell

<!-- /new-project ersetzt die beiden Blöcke unten aus den Antworten. Struktur für die
     spätere docker-compose-Automatisierung maschinenlesbar halten. -->

## Backend
{{BACKEND}}

## Frontend
{{FRONTEND}}

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

## Neue API-Route & API-Doku
<!-- Konstante Regel; die projektspezifische Ausprägung steht in an_project/docs/dev-guide.md. -->
- Eine neue Route entsteht als Controller-Action im zuständigen **Bundle**
  (Symfony `#[Route(...)]`).
- Die **API-Doku wird automatisch generiert**, nicht von Hand gepflegt: Symfony via
  `nelmio/api-doc-bundle` bzw. API Platform (OpenAPI/Swagger), Angular via `compodoc`.
- Der konkrete Regenerier-Befehl und der Ablageort der generierten Doku gehören ins
  `an_project/docs/runbook.md` und `an_project/docs/dev-guide.md`.

## Verengung der Baseline
<!-- Fachliche Commit-Scopes (in an_project/docs/git.md) und bewusste Abweichungen. -->
