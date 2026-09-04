# Tests

Zwei Suiten, bewusst getrennt:

| Suite | Verzeichnis | Braucht |
|---|---|---|
| `unit` | `tests/Unit` | nichts — muss auf jedem Checkout grün sein |
| `integration` | `tests/Integration` | die Umgebung aus `docker-compose.yml` und eine durchgeführte Installation |

Der Grund für die Trennung: Läge beides zusammen, stünde die ganze Suite still, sobald kein
Container läuft. Eine Suite, die häufig aus Umgebungsgründen rot ist, wird nicht mehr gelesen.

```sh
./custom/vendor/bin/phpunit                          # beide Suiten
./custom/vendor/bin/phpunit --testsuite unit         # ohne Datenbank
./custom/vendor/bin/phpunit --coverage-text          # braucht Xdebug oder PCOV
```

## Integrationstests ausführen

Sie brauchen eine **laufende, installierte** Instanz und werden sonst sauber übersprungen:

```sh
# 1. Datenbank und Installation (siehe an_project/docs/runbook.md)
docker compose up -d
php bin/console.php appcms:install --db-host=127.0.0.1 --db-port=3307 \
    --db-name=contentfly --db-user=contentfly --db-pass=contentfly \
    --db-strategy=guid --admin-password='dev-only-secret'

# 2. Testserver — mit dem Router und ohne Debug-Ausgabe
APP_ENV=production APP_DEBUG=0 php -S 127.0.0.1:8145 tests/router.php &

# 3. Suite gegen diese Instanz
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit
```

**`tests/router.php` ist nicht optional.** Ohne ihn schickt der eingebaute Server *jede* Anfrage
durch `index.php` — auch die für eine Datei, die auf der Platte liegt. Apache tut das nicht, und
die Auslieferung von Dateien hängt genau daran.

**`APP_DEBUG=0`** verhindert, dass der Debug-Exception-Handler die Antworten der Anwendung
überdeckt. Ganz behoben ist das damit nicht — siehe Task `000-000-0006`.

Nach der Installation trägt `custom/config.php` echte Zugangsdaten. **Vor dem Commit die
Platzhalter wiederherstellen**, sonst landen sie in der Vorlage.

## Was `tests/bootstrap.php` tut — und was nicht

Es lädt **nicht** `lib/contentfly/bootstrap.php`. Der baut die komplette Silex-Anwendung auf,
verlangt eine konfigurierte Datenbank und startet eine Session — für einen Test, der eine
einzelne Klasse prüft, ist das weder nötig noch erwünscht.

Stattdessen: beide Autoloader, `ROOT_DIR`, die Versionsdateien und die Konstanten, die
Entity-Klassen schon beim Laden in ihren Annotationen lesen (`APPCMS_ID_TYPE` und Verwandte).
Ohne sie scheitert bereits das Einlesen der Metadaten.

Ein Test, der die volle Anwendung braucht, baut sie sich selbst auf — und gehört damit nach
`tests/Integration`.

## Zeitzone

`phpunit.xml.dist` pinnt `date.timezone` auf UTC: dieselbe Zone, in der die API ihre
Zeitstempel ausliefert. Der CLI-Schalter übersteuert das **nicht** — PHPUnit wendet den
Konfigurationsblock danach an, und PHP ignoriert `TZ`, solange `date.timezone` gesetzt ist.
Beides scheitert stillschweigend.
