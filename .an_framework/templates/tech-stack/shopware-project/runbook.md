<!-- PURPOSE: RUNBOOK-Vorlage für ein Shopware-6-Projekt (kopiert von /new-project nach
     an_project/docs/runbook.md). Freie Prosa, KEIN Work-Item-Frontmatter. -->

# Runbook — lokales Setup (Shopware 6 Projekt)

## Voraussetzungen
- Docker & Docker Compose (bzw. Dockware / Devenv, je nach Team-Setup)
- PHP, Composer, Node.js — Versionen laut Shopware-Anforderungen

## 1. Services hochfahren
```sh
docker compose up -d
```

## 2. Shopware installieren
```sh
composer install
bin/console system:install --basic-setup    # frische Instanz
# oder bei bestehender Datenbank:
bin/console system:update:finish
```

## 3. Assets bauen
```sh
bin/console theme:compile
./bin/build-storefront.sh
./bin/build-administration.sh
```

## 4. Routine nach Änderungen
```sh
bin/console cache:clear
bin/console database:migrate --all
bin/console dal:refresh:index
```

## 5. Zugriff
- Storefront: http://localhost
- Administration: http://localhost/admin

## Troubleshooting
<!-- Cache, JWT-Keys (bin/console system:generate-jwt-secret), Elasticsearch, Message-Queue. -->
