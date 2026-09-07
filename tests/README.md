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

## Die Versandfalle für `/api/mail`

`/api/mail` ist der einzige Endpunkt mit Aussenwirkung. **Kein Testlauf darf eine Mail
verschicken** — und das gehört nachgewiesen, nicht angenommen. Dafür bekommt der Testserver
ein Fangskript als `sendmail_path`:

```sh
FALLE=/tmp/contentfly-mailfalle
mkdir -p "$FALLE"
cat > "$FALLE/sendmail" <<'SKRIPT'
#!/bin/sh
# Faengt alles ab, was mail() zustellen wollte. Stellt NICHTS zu.
cat >> "$(dirname "$0")/postausgang.log"
echo "--- ENDE MAIL ---" >> "$(dirname "$0")/postausgang.log"
exit 0
SKRIPT
chmod +x "$FALLE/sendmail"

# Testserver mit der Umleitung
APP_ENV=production APP_DEBUG=0 \
  php -d sendmail_path="$FALLE/sendmail" -S 127.0.0.1:8145 tests/router.php &

# Suite mit dem Pfad zur Falle
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
CONTENTFLY_TEST_MAIL_TRAP="$FALLE" \
  ./custom/vendor/bin/phpunit
```

`MailApiTest` prüft die Sicherung selbst, in beide Richtungen:

- **Fängt das Skript?** Der Test löst es einmal absichtlich über einen eigenen PHP-Prozess aus
  und schneidet den Eintrag danach wieder heraus.
- **Benutzt der Server es?** Über `/__test/sendmail-path` — ein Diagnosepfad, den
  `tests/router.php` beantwortet und den es in keiner Installation gibt. Läuft der Server ohne
  die Umleitung, scheitert der Test und nennt den echten MTA.

**Ohne `CONTENTFLY_TEST_MAIL_TRAP` werden die betroffenen Tests übersprungen, nicht
durchgewinkt.** Das ist Absicht: Solange der Nachweis fehlt, wird der Endpunkt nicht mit einer
Zieladresse aufgerufen.

> Heute ist die Falle streng genommen unnötig — `/api/mail` scheitert an einer undefinierten
> Konstanten, bevor `mail()` überhaupt drankommt (`000-000-0016`). Die Sicherung hängt bewusst
> **nicht** an diesem Fehler: Wer ihn behebt, soll nicht gleichzeitig den Schutz entfernen.

Nach der Installation trägt `custom/config.php` echte Zugangsdaten. **Vor dem Commit die
Platzhalter wiederherstellen**, sonst landen sie in der Vorlage.

## Eine neue Integrationstest-Datei anlegen

Erben von `Tests\Integration\IntegrationTestCase` — nicht von PHPUnits `TestCase`. Die Basis
bringt mit, was sonst jede Datei selbst nachbauen müsste:

| Methode | Zweck |
|---|---|
| `login()` | meldet neu an, liefert einen frischen Token |
| `token()` | liefert einen Token und behält ihn für die Testklasse |
| `postJson($pfad, $daten, $token = null)` | → `[Status, Rumpf als Array, Kopfzeilen]` |
| `get($pfad, $token = null)` | → `[Status, Rumpf als String, Kopfzeilen]` |
| `kopfzeile($kopf, $name)` | liest eine einzelne Kopfzeile, z. B. `Location` |
| `pdo()` | Verbindung zur Testdatenbank |
| `nachTestLoeschen($tabelle, $id)` | meldet eine Zeile an, die `tearDown()` entfernt |
| `nachTestVerzeichnisLoeschen($pfad)` | meldet ein Verzeichnis an, das `tearDown()` samt Inhalt entfernt |
| `testbenutzer($rechte, $gruppe)` | legt Gruppe, Nicht-Admin und Berechtigungen an → `[Token, Benutzer-Id, Gruppen-Id]` |

Das Überspringen ohne `CONTENTFLY_TEST_BASE_URL` erledigt die Basis ebenfalls — ein eigenes
`setUp()` braucht es dafür nicht. Wer eines schreibt, ruft `parent::setUp()` auf.

```php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

class BeispielApiTest extends IntegrationTestCase
{
    public function testEtwas(): void
    {
        [$status, $body] = $this->postJson('/api/single', array(...), $this->token());
        $this->assertSame(200, $status);
    }
}
```

### Tests mit Berechtigungen

`testbenutzer()` legt in einem Aufruf eine Gruppe, einen Nicht-Admin darin und dessen
Entity-Berechtigungen an und meldet ihn an:

```php
use Areanet\PIM\Entity\Permission;

[$token] = $this->testbenutzer(array(
    'PIM\\Tag' => array('readable' => Permission::ALL, 'writable' => Permission::OWN),
));
```

Fehlende Schlüssel sind `Permission::NONE`. Über den zweiten Parameter lassen sich
Gruppenfelder setzen (`apiQueryEnabled`, `languages`).

> **Die Stufen gehören als Konstanten übergeben, nicht als Zahlen.** Sie sind nicht
> aufsteigend geordnet: `NONE` ist 0, `OWN` ist 1, `ALL` ist 2 und `GROUP` ist 3. Wer sie als
> Rangfolge liest, irrt.

**Testdaten entstehen über `pdo()`, nicht über die Schreib-Endpunkte.** Ein Lesetest, dessen
Vorbedingung über einen Pfad läuft, den er selbst nicht prüft, verliert seine Aussagekraft —
und Story `008-001` soll ausdrücklich nicht von `008-002` abhängen. Die Zugangsdaten kommen aus
`CONTENTFLY_TEST_DB_*`; die Standardwerte entsprechen der `docker-compose.yml`.

> Die Basisklasse wird von `tests/bootstrap.php` per `require_once` geladen, nicht über den
> Autoloader: `custom/composer.json` mappt `Custom\Tests\`, die Testklassen liegen aber unter
> `Tests\` — und PHPUnit lädt von sich aus nur Dateien, die auf `Test.php` enden. Räumt Epic
> `006` die Autoload-Situation auf, kann daraus ein PSR-4-Mapping werden.

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
