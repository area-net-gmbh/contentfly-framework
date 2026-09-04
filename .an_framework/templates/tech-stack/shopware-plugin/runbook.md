<!-- PURPOSE: RUNBOOK-Vorlage für ein Shopware-6-Plugin (kopiert von /new-project nach
     an_project/docs/runbook.md). Das Plugin läuft INNERHALB einer Shopware-Instanz. -->

# Runbook — lokales Setup (Shopware 6 Plugin)

Ein Plugin braucht eine laufende Shopware-6-Instanz als Wirt.

## Voraussetzungen
- Laufende Shopware-6-Instanz (siehe deren Runbook)
- Plugin liegt unter `custom/plugins/<PluginName>` oder ist per Composer eingebunden

## 1. Plugin registrieren & aktivieren
```sh
bin/console plugin:refresh
bin/console plugin:install --activate <PluginName>
bin/console cache:clear
```

## 2. Assets bauen (falls das Plugin welche mitbringt)
```sh
./bin/build-administration.sh
./bin/build-storefront.sh
```

## 3. Nach Änderungen
```sh
bin/console plugin:update <PluginName>
bin/console cache:clear
```

## 4. Tests
```sh
vendor/bin/phpunit
```

## Troubleshooting
<!-- Plugin nicht sichtbar → plugin:refresh; Migrations; DI-Container-Cache. -->
