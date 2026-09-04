<!-- PURPOSE: TECH-STACK-Vorlage (kopiert von /new-project nach an_project/docs/tech-stack.md).
     Freie Prosa, KEIN Work-Item-Frontmatter. Verengt die Baseline für dieses Projekt. -->
<!-- FUTURE: Neben dieser Datei kann später eine docker-compose.template.yml liegen,
     die /new-project für eine Test-Umgebung ins Projekt kopiert. Noch nicht gebaut. -->

# Tech-Stack: Shopware 6 — Projekt (Full-Shop)

## Versionen
- Shopware **6.6.x** (LTS-nah; vor Projektstart die konkrete Minor festnageln)
- PHP **8.2** oder **8.3**
- MySQL **8.0** oder MariaDB **10.11**
- Node **20 LTS** (Build von Administration/Storefront), Composer **2.x**

## Build / Test / Run
- `composer install`
- `bin/console system:install --basic-setup` (Erstinstallation)
- Storefront-Assets: `bin/build-storefront.sh` · Admin: `bin/build-administration.sh`
- Cache & Migrationen: `bin/console cache:clear`, `bin/console database:migrate --all`
- Tests: `vendor/bin/phpunit`; Datenmodell prüfen: `bin/console dal:validate`

## Struktur & Konventionen
- Eigener Code als Plugins unter `custom/plugins/<PluginName>/`, nicht im Core.
- Konfiguration über `config/` und Plugin-`config.xml`, nie Core-Dateien patchen.
- PSR-12; Shopware Coding Standards (`vendor/bin/ecs`, sofern eingerichtet).
- Migrationen versioniert unter `src/Migration/`, niemals nachträglich ändern.

## Verengung der Baseline
- Commit-Scopes (in `an_project/docs/git.md`) fachlich, z. B. `checkout`, `catalog`,
  `admin`, `storefront`, `migration`.
- Keine direkten DB-Änderungen ohne Migration.
