<!-- PURPOSE: RUNBOOK-Vorlage für "Individual" (kopiert von /new-project nach
     an_project/docs/runbook.md). /new-project füllt {{BACKEND}} und {{FRONTEND}} aus den
     Interview-Antworten. Freie Prosa, KEIN Work-Item-Frontmatter. Erklärt einem Developer,
     wie er das Projekt nach dem Checkout lokal zum Laufen bringt. -->

# Runbook — lokales Setup (Individual)

Schritt für Schritt vom frischen Checkout zur laufenden lokalen Umgebung. Das Team ergänzt
die konkreten Befehle je Stack und ersetzt die Platzhalter unten.

## Voraussetzungen
<!-- Vom Team ergänzen: Docker, Node-Version, PHP/Composer, weitere Tools. -->
- Docker & Docker Compose
- PHP + Composer (Backend: PHP 8.5 als Zielplattform · MySQL · Doctrine ORM · Symfony 7 als
  Migrationsziel — aktuell noch Silex 2)

## 1. Services hochfahren
```sh
docker compose up -d
```
<!-- Welche Container starten (DB, Cache, Mailhog …) und auf welchen Ports. -->

## 2. Backend installieren — PHP · MySQL · Doctrine ORM
```sh
composer install
cp .env .env.local        # lokale Overrides
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate
bin/console doctrine:fixtures:load   # Seed-Daten, falls vorhanden
```
<!-- Die Befehle stammen aus der Vorlage und gelten für den Symfony-Zielkernel.
     Für den aktuellen Silex-Stand vom Team ergänzen (bin/, custom/app.php). -->

## 3. Frontend installieren
Entfällt — dieses Projekt hat kein Frontend (reines Backend/API).

## 4. Zugriff
<!-- URLs/Ports: Backend-API, DB, Mailhog … -->
- Backend-API: http://localhost:8000

## 5. API-Doku neu generieren
<!-- Befehl, der die OpenAPI/Swagger-Doku aktualisiert — Details in an_project/docs/dev-guide.md. -->

## Troubleshooting
<!-- Häufige Stolpersteine: Ports belegt, DB-Verbindung, Cache leeren (bin/console cache:clear). -->
