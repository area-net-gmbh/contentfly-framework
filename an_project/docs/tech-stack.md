<!-- PURPOSE: TECH-STACK-Vorlage für "Individual" (kopiert von /new-project nach
     an_project/docs/tech-stack.md). /new-project füllt {{BACKEND}} und {{FRONTEND}}
     aus den Interview-Antworten. Freie Prosa, KEIN Work-Item-Frontmatter. -->
<!-- FUTURE: Neben dieser Datei kann später eine docker-compose.template.yml liegen,
     die /new-project für eine Test-Umgebung ins Projekt kopiert. Noch nicht gebaut. -->

# Tech-Stack: Individuell

<!-- /new-project ersetzt die beiden Blöcke unten aus den Antworten. Struktur für die
     spätere docker-compose-Automatisierung maschinenlesbar halten. -->

## Backend
PHP 8.5 (Zielplattform) · MySQL · Doctrine ORM · **Symfony 7.4 LTS**. Der Kernel steht seit
Epic `009` auf Symfony 7.4; Silex 2, Pimple und `knplabs/console-service-provider` sind aus dem
Baum. HttpFoundation, HttpKernel, EventDispatcher, Routing und Console kommen direkt aus
Symfony, der Container ist ein eigener (`Areanet\PIM\Classes\Kernel\Container`, rund 120
Zeilen). Doctrine steht seit Epic `010` auf **ORM 3.7** über DBAL 3.10; die Entities tragen
**PHP-Attribute** statt Annotationen, und Query- wie Metadaten-Cache laufen über PSR-6
(`symfony/cache`).

**Fixiert auf Symfony 7.4 LTS** (Bugfixes bis Nov 2028, Security bis Nov 2029), nicht auf
Symfony 8.x. Symfony 8.0 hat denselben Funktionsumfang wie 7.4 — der Major entfernt nur
deprecated Code und hebt das PHP-Minimum auf 8.4. 7.4 verlangt nur PHP ≥ 8.2 und entkoppelt
damit die PHP- von der Kernel-Entscheidung — was zählt, solange Alt- und Neustand nebeneinander
laufen und solange Bestandsprojekte unterschiedliche PHP-Versionen mitbringen. Begründung und
Alternativen: `an_project/docs/architecture.md`, *Key decisions*.

**Pflicht dazu — deprecation-frei bauen:** Der spätere Sprung auf Symfony 8.4 LTS (erwartet
Nov 2027) ist nur dann ein reiner Constraint-Bump, wenn keine in 8.0 entfernten APIs benutzt
werden. Deprecation-Log und PHPStan stehen deshalb als Gate in der CI.

**Der Stand ist eingelöst, nicht nur zugesagt** (`009-003`, fortgeschrieben mit `010-003`): Das
Laufzeit-Log meldet **0 Deprecations bei 0 Ausnahmen**, PHPStan `[OK] No errors` und
blockierend, `composer audit --locked` **0 Advisories und 0 abandoned Pakete**.

Von den acht PHPStan-Ausnahmen, die Epic `009` an `010` übergeben hat, ist **eine** übrig — und
die kommt aus DBAL, nicht aus dem ORM. Von fünf abandoned Paketen sind **null** übrig, und der
Audit-Schalter steht deshalb auf `--abandoned=fail`: Ein abandoned Paket macht den Lauf jetzt
wieder rot.

Alle drei Gates tragen dieselbe Regel: Eine Ausnahme, die nicht mehr greift, macht den Lauf rot.
Sie hat sich im Epic `010` fünfmal gemeldet, und jedes Mal war ein Muster gegenstandslos
geworden.

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
- Routen entstehen weiterhin über `custom/app.php` (`$app['routeManager']->mount()`). Der
  Kernel-Wechsel hat daran bewusst nichts geändert — Epic `009` hat den Unterbau getauscht und
  die Schnittstelle gehalten. Die Bundle-Regel greift erst, wenn ein Epic sie einführt; im
  Schnitt für `009` ist keine Bundle-Struktur enthalten.

## Verengung der Baseline
<!-- Fachliche Commit-Scopes (in an_project/docs/git.md) und bewusste Abweichungen. -->
