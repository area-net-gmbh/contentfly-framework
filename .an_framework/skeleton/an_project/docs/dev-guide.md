<!-- PURPOSE: Entwickler-Leitfaden für DIESES Projekt — Backend-/Frontend-Struktur und wie
     man neue Bausteine (z. B. eine API-Route) anlegt. Overlay: das Projekt füllt es aus.
     Die KONSTANTEN Struktur-Regeln (Symfony = immer Bundles, Angular = src/modules + src/shared)
     stehen read-only in .an_framework/templates/tech-stack/<slug>/stack.md → an_project/docs/tech-stack.md;
     hier steht die konkrete Ausprägung für dieses Projekt. Nur bei Full-Projekten vorhanden
     (im Slim-Profil wird diese Datei nicht angelegt). -->

# Dev-Guide — Struktur & neue Bausteine

## Backend-Struktur
<!-- Die Bundles dieses Projekts und ihre Verantwortlichkeiten. Symfony wird IMMER in
     Bundles organisiert (wie Shopware 6), egal ob 1 oder 10 Bundles — siehe tech-stack.md. -->

| Bundle | Verantwortung |
|---|---|
| <!-- z. B. CatalogBundle --> | <!-- Produkte, Kategorien --> |

## Frontend-Struktur
<!-- Modul-Map: src/modules/<feature> + src/shared. Angular (mit/ohne Ionic) wird IMMER so
     aufgebaut — siehe tech-stack.md. -->

| Modul | Verantwortung |
|---|---|
| <!-- z. B. src/modules/checkout --> | <!-- Warenkorb, Bezahlung --> |
| `src/shared` | Wiederverwendbare Components, Services, Pipes |

## Neue API-Route hinzufügen
<!-- Konkrete Schritte für DIESEN Code: in welchem Bundle der Controller liegt, welches
     Route-Attribut, wie die Route getestet wird. -->
1. Controller im zuständigen Bundle anlegen (`#[Route(...)]`).
2. <!-- Request/Response-DTO, Validierung, Service-Aufruf. -->
3. API-Doku neu generieren (siehe unten).

## API-Dokumentation
<!-- Die API-Doku wird AUTOMATISCH generiert, nicht von Hand gepflegt. Womit (z. B.
     nelmio/api-doc-bundle bzw. api-platform für Symfony; compodoc für Angular), welcher
     Befehl sie neu baut, und wo das Ergebnis (OpenAPI/Swagger) liegt. -->
- Generator: <!-- z. B. nelmio/api-doc-bundle -->
- Befehl: <!-- z. B. bin/console nelmio:apidoc:dump -->
- Ausgabe: <!-- z. B. http://localhost:8000/api/doc -->
