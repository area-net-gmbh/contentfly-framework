<!-- PURPOSE: TECH-STACK-Vorlage (kopiert von /new-project nach an_project/docs/tech-stack.md).
     Freie Prosa, KEIN Work-Item-Frontmatter. Verengt die Baseline für dieses Projekt. -->
<!-- FUTURE: Neben dieser Datei kann später eine docker-compose.template.yml liegen,
     die /new-project für eine Test-Umgebung ins Projekt kopiert. Noch nicht gebaut. -->

# Tech-Stack: Shopware 6 — Plugin

## Versionen
- Ziel-Shopware **6.6.x** (Kompatibilitätsbereich in `composer.json` über
  `shopware/core`-Constraint festlegen, z. B. `~6.6.0`)
- PHP **8.2+**, Composer **2.x**, Node **20 LTS** für Admin-/Storefront-Anteile

## Build / Test / Run
- Entwicklung in einer Shopware-Instanz unter `custom/plugins/<PluginName>/`.
- Aktivieren: `bin/console plugin:refresh && bin/console plugin:install --activate <PluginName>`
- Admin/Storefront des Plugins bauen: `bin/build-administration.sh` / `bin/build-storefront.sh`
- Tests: `vendor/bin/phpunit` gegen die Plugin-Test-Suite.
- Auslieferung: `zip` des Plugin-Ordners bzw. `bin/console plugin:zip:create`.

## Struktur & Konventionen
- Bootstrap-Klasse `<PluginName>.php` im `src/`-Root, PSR-4 aus `composer.json`.
- Ressourcen unter `src/Resources/` (config, views, app, public).
- Services über `src/Resources/config/services.xml`, keine Core-Services überschreiben
  ohne Decorator.
- Migrationen unter `src/Migration/`, additiv und idempotent.

## Verengung der Baseline
- Ein Plugin = ein Repository = ein fachlicher Scope.
- SemVer für das Plugin; Breaking Changes nur mit Major-Bump.
