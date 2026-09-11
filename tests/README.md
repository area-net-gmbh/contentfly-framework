# Tests

> **Diese Suite ist die Abnahmegrundlage für den Kernel-Tausch.** Der Kernel-Tausch gilt als
> gelungen, wenn sie **ohne inhaltliche Änderung** grün bleibt; eine Testanpassung ist ein
> Verhaltenswechsel und braucht eine Begründung. Was das genau heisst — und was die Suite
> ausdrücklich **nicht** abdeckt — steht in `an_project/docs/technical.md`, Abschnitt *Die
> Testsuite ist die Abnahmegrundlage*. Wer hier einen Test ändert, liest das zuerst.

Zwei Suiten, bewusst getrennt:

| Suite | Verzeichnis | Braucht |
|---|---|---|
| `unit` | `tests/Unit` | nichts — muss auf jedem Checkout grün sein |
| `integration` | `tests/Integration` | die Umgebung aus `docker-compose.yml` und eine durchgeführte Installation |

Der Grund für die Trennung: Läge beides zusammen, stünde die ganze Suite still, sobald kein
Container läuft. Eine Suite, die häufig aus Umgebungsgründen rot ist, wird nicht mehr gelesen.

**Vorher: `composer install`.** PHPUnit liegt in `require-dev` und `vendor/` seit Story
`006-003` nicht mehr im Repo — ohne den Schritt gibt es kein `./vendor/bin/phpunit`, und zwar
ohne Hinweis darauf, warum. Der vollständige Ablauf steht in `an_project/docs/runbook.md`.

```sh
composer install                              # ZUERST — sonst existiert phpunit nicht
./vendor/bin/phpunit                          # beide Suiten
./vendor/bin/phpunit --testsuite unit         # ohne Datenbank
./vendor/bin/phpunit --coverage-text          # braucht Xdebug oder PCOV
```

## Integrationstests ausführen

Sie brauchen eine **laufende, installierte** Instanz und werden sonst sauber übersprungen:

```sh
# 0. Abhaengigkeiten — ohne sie gibt es weder phpunit noch einen Autoloader
composer install

# 1. Datenbank und Installation (siehe an_project/docs/runbook.md)
docker compose up -d
php bin/console.php appcms:install --db-host=127.0.0.1 --db-port=3307 \
    --db-name=contentfly --db-user=contentfly --db-pass=contentfly \
    --db-strategy=guid --admin-password='dev-only-secret'

# 2. Versandfalle — damit kein Lauf eine Mail nach draussen schickt
FALLE=/tmp/contentfly-mailfalle
mkdir -p "$FALLE"
printf '#!/bin/sh\ncat >> "$(dirname "$0")/postausgang.log"\nexit 0\n' > "$FALLE/sendmail"
chmod +x "$FALLE/sendmail"

# 3. Testserver — mit Router, Versandfalle und ohne Fehlerausgabe im Antwortstrom
#
#    SECURITY_JWT_SECRET und CONTENTFLY_BEISPIEL_PROVIDER gehen an die ANWENDUNG, nicht an die
#    Suite: Das erste laesst sie JWT ausstellen (013-003), das zweite speist die
#    Provider-Vorlage (013-004). Ohne sie stellt der Login keine JWT aus und die Vorlage laesst
#    niemanden herein — beides richtig, aber dann haben die zugehoerigen Tests nichts zu messen.
APP_ENV=production APP_DEBUG=0 \
  SECURITY_JWT_SECRET=dev-only-jwt-secret-mit-genug-laenge \
  CONTENTFLY_BEISPIEL_PROVIDER='extern-eins:dev-only-provider-secret:CN=Redaktion' \
  php -d display_errors=Off -d log_errors=On -d sendmail_path="$FALLE/sendmail" \
      -S 127.0.0.1:8145 tests/router.php &

# 4. Suite gegen diese Instanz
#
#    Die CONTENTFLY_TEST_*-Werte fuer JWT und Provider muessen zu denen oben passen: Die Suite
#    zeigt vor, was der Server erwartet.
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
CONTENTFLY_TEST_MAIL_TRAP="$FALLE" \
CONTENTFLY_TEST_JWT_SECRET=dev-only-jwt-secret-mit-genug-laenge \
CONTENTFLY_TEST_PROVIDER='extern-eins:dev-only-provider-secret:CN=Redaktion' \
  ./vendor/bin/phpunit

# 5. Die Vorlage wiederherstellen — Schritt 1 hat Zugangsdaten hineingeschrieben
git checkout HEAD -- custom/config.php
```

Ohne Schritt 2 und `CONTENTFLY_TEST_MAIL_TRAP` überspringt sich `VersandfalleTest`; in einer
Pipeline wird der Lauf dann rot (siehe *Der Wächter* weiter unten). Die ausführliche Fassung
der Versandfalle steht im Abschnitt darunter.

**`tests/router.php` ist nicht optional.** Ohne ihn schickt der eingebaute Server *jede* Anfrage
durch `index.php` — auch die für eine Datei, die auf der Platte liegt. Apache tut das nicht, und
die Auslieferung von Dateien hängt genau daran.

**`APP_DEBUG=0`** verhindert, dass der Debug-Exception-Handler die Antworten der Anwendung
überdeckt. Der Rest davon ist mit `000-000-0006` behoben: Ein PHP-Fehler kommt seither als
JSON-Antwort der Anwendung an und nicht mehr als Symfonys „Whoops"-Seite. `APP_DEBUG=0`
bleibt trotzdem gesetzt, weil es die Produktionseinstellung ist und die Suite messen soll,
was eine Installation ausliefert.

**`display_errors=Off` ist nicht kosmetisch.** PHP schreibt eine Deprecation direkt in den
Antwortstrom. Passiert das, bevor der Kernel den Statuscode setzt, sind die Header schon
unterwegs — und die Antwort trägt `200`, obwohl die Anwendung `405` oder `500` meint. Das gilt
unverändert für den Symfony-Kernel aus Epic `009`; die Reihenfolge Ausgabe-vor-Header ist eine
Eigenschaft von PHP, nicht des Frameworks. Beim ersten
CI-Lauf sind daran sechs Tests gescheitert, die lokal grün waren; mit `display_errors=Off`
laufen alle 232 durch. Es ist zugleich die Produktionseinstellung: Eine Instanz, die
Deprecations ausliefert, verrät Dateipfade an jeden Aufrufer. Dass das Framework sie bei
`APP_DEBUG=0` **nicht erzwingt**, ist ein eigener Befund — `000-000-0018`.

`log_errors=On` sorgt dafür, dass die Deprecations nicht verschwinden, sondern im Serverlog
stehen. Das ist die Quelle, aus der das „0 Deprecations"-Gate aus `006-005` später liest.

## Der Wächter gegen stille Übersprünge

`tests/Integration/UmgebungsWaechterTest.php` löst das eigentliche Risiko einer Pipeline:
**eine grüne Suite, die nichts geprüft hat.** Fehlt `CONTENTFLY_TEST_BASE_URL`, überspringen
sich alle Integrationstests, PHPUnit meldet `OK, but some tests were skipped` — und der Job
wird grün.

Der Wächter prüft deshalb: Ist `CI` gesetzt (GitLab und die meisten anderen tun das von
selbst), **müssen** `CONTENTFLY_TEST_BASE_URL`, `CONTENTFLY_TEST_MAIL_TRAP` und
`CONTENTFLY_TEST_ADMIN_PASS` da sein. Fehlt eine, ist der Lauf rot, und die Meldung nennt die
Variable und warum sie nicht durchgewinkt wird.

Drei weitere Prüfungen laufen **auch lokal**, sobald die Variablen gesetzt sind: dass unter
der Basisadresse wirklich etwas antwortet, dass die **Testdatenbank** erreichbar *und
installiert* ist, und dass die Versandfalle ein ausführbares Fangskript enthält. Eine gesetzte
Variable sagt nichts darüber, ob dahinter etwas läuft.

Die Datenbankprüfung kam nachträglich dazu, nachdem der Fall real eingetreten war: Bei
gestopptem Container meldete die Suite **91 Errors** — lauter `PDOException: Connection
refused` aus einzelnen Tests, und keiner davon sagte, dass schlicht die Datenbank fehlt.

| `CI` | Variablen | Ergebnis |
|---|---|---|
| nicht gesetzt | fehlen | grün, Integrationstests übersprungen |
| nicht gesetzt | gesetzt | grün, alles läuft |
| `true` | fehlen | **rot** (Exit 1), Meldung nennt die Variable |
| `true` | gesetzt | grün, alles läuft |

Er erbt bewusst **nicht** von `IntegrationTestCase` — sonst überspränge er sich unter genau
den Bedingungen selbst, vor denen er warnt. Wovor er nicht schützt: ein einzelnes
`markTestSkipped()`, das jemand einem Test hinzufügt. Er prüft die Vorbedingungen eines
Integrationslaufs, nicht jeden denkbaren Übersprung.

## In der Pipeline

`.gitlab-ci.yml` fährt genau diesen Ablauf; die Schritte stehen in `tools/ci/`, damit man sie
**lokal in Docker nachspielen kann** — eine Pipeline-Definition, deren Schritte man nur in der
Pipeline ausprobieren kann, ist beim Suchen eines Fehlers nutzlos.

Der Testlauf selbst:

```sh
sh tools/ci/install-php-extensions.sh    # pdo_mysql, gd, ldap und unzip
sh tools/ci/install-composer.sh          # nur noetig, wo composer noch fehlt
composer install                          # seit 006-003 die einzige Quelle des Baums
sh tools/ci/prepare-test-environment.sh  # warten, installieren, Versandfalle, Server
./vendor/bin/phpunit
sh tools/ci/deprecations-pruefen.sh      # liest das Serverlog, NICHT die Ausgabe oben
```

**Der letzte Schritt gehört dazu, auch wenn PHPUnit rot war.** Die `.gitlab-ci.yml` hebt den
Exit-Code von PHPUnit deshalb auf und macht ihn erst danach wirksam — sonst bräche GitLab die
Liste beim ersten Fehlschlag ab, und das Deprecation-Gate wäre genau dann blind, wenn am meisten
passiert ist.

Die beiden Prüfungen der Stage `check` brauchen weder Datenbank noch Testserver:

```sh
sh tools/ci/audit.sh                     # composer audit --locked, blockierend
sh tools/ci/audit-ausnahmen-pruefen.sh   # meldet Ausnahmen, die nicht mehr greifen
./vendor/bin/phpstan analyse --memory-limit=512M   # nicht blockierend
```

Was diese Gates prüfen und was bei einem Fund zu tun ist, steht in
`an_project/docs/deployment.md` unter *Die Gates*.

### Gegen eine Installation ausserhalb des Repos prüfen

**Seit `007-001-0005`.** Bezieht ein Projekt das Framework als Paket, liegt die Anwendung nicht
mehr im selben Baum wie die Suite. `CONTENTFLY_TEST_PROJEKT` sagt dann, wo sie liegt:

```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8171 \
CONTENTFLY_TEST_PROJEKT=/pfad/zum/projekt \
./vendor/bin/phpunit
```

Daran hängen zwei Dinge, die vorher stillschweigend der Baum der Suite waren: das
`data/`-Verzeichnis, das zwischen Tests geleert wird, und `bin/console.php`, das einige Tests
aufrufen. **Eine Angabe für beides und nicht zwei** — zwei könnten auseinanderlaufen, und dann
prüfte ein Lauf zwei verschiedene Installationen, ohne es zu merken.

**Ohne die Variable bleibt alles wie bisher.** Das ist der Normalfall und der einzige, den die
Pipeline kennt.

**Bleibt ein Schritt stumm stehen, ist das ein Fehler im Skript, nicht im Werkzeug.** Seit
`000-000-0029` gibt jeder Schritt, der seine Ausgabe wegschiebt, im Fehlerfall die letzten
Zeilen seines Logs aus; wie das geht und warum, steht in `tools/ci/schritt.sh` und in
`an_project/docs/deployment.md` unter *Ein Schritt, der scheitert, sagt woran*.

## Die Versandfalle

**Kein Testlauf darf eine Mail verschicken** — und das gehört nachgewiesen, nicht angenommen.
Dafür bekommt der Testserver ein Fangskript als `sendmail_path`:

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
  ./vendor/bin/phpunit
```

`VersandfalleTest` prüft die Sicherung selbst, in beide Richtungen:

- **Fängt das Skript?** Der Test löst es einmal absichtlich über einen eigenen PHP-Prozess aus
  und schneidet den Eintrag danach wieder heraus.
- **Benutzt der Server es?** Über `/__test/sendmail-path` — ein Diagnosepfad, den
  `tests/router.php` beantwortet und den es in keiner Installation gibt. Läuft der Server ohne
  die Umleitung, scheitert der Test und nennt den echten MTA.

**Ohne `CONTENTFLY_TEST_MAIL_TRAP` wird der Test übersprungen, nicht durchgewinkt.** Das ist
Absicht: Solange der Nachweis fehlt, gilt die Sicherung als nicht belegt.

> **Der Anlass ist weg, die Sicherung bleibt.** `/api/mail` war der einzige Endpunkt mit
> Aussenwirkung und ist mit `000-000-0016` entfernt — er verschickte seit dem Sprung auf PHP 8
> ohnehin nichts. Die Falle hing nie an ihm: `$app['mailer']` steht Projekten weiter zur
> Verfügung, und `custom/app.php` ist die Vorlage, in die sie es einbauen. Eine Sicherung mit
> ihrem ersten Anlass abzubauen hiesse, sie in dem Moment aufzugeben, in dem niemand mehr
> hinsieht.

### 4. Die Vorlage wiederherstellen — fester Schritt, nicht Kür

Die Installation aus Schritt 1 schreibt Host, Benutzer und Passwort in `custom/config.php` —
eine Datei, die **versioniert im Repo liegt**, weil sie die Vorlage ist. Der Testlauf ist erst
zu Ende, wenn sie wieder eine ist:

```sh
git checkout HEAD -- custom/config.php
```

**Das `HEAD` ist wichtig.** Ist die Datei bereits gestagt, holt `git checkout -- <pfad>` sie
aus dem *Index* zurück und schreibt die installierte Fassung erneut in den Arbeitsbaum — es
sieht aus wie eine Wiederherstellung und ist keine.

Damit niemand daran denken muss, gibt es zwei Netze: den `pre-commit`-Hook aus `tools/hooks/`
(einmalig mit `git config core.hooksPath tools/hooks` aktivieren) und den Pipeline-Job
`check:template-config`. Beide rufen `tools/check-template-config.sh` auf und melden dasselbe.
Der Hook fängt früher, der Job fängt immer. Einrichtung: `an_project/docs/runbook.md`.

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

> **Die Basisklasse kommt ganz normal über den Autoloader.** `composer.json` mappt `Tests\` auf
> `tests/`, in `autoload-dev` — Testcode landet damit nicht im Deployment-Artefakt. Ein eigenes
> `require_once` in `tests/bootstrap.php` braucht es seit `006-004-0004` nicht mehr; davor lag
> das Mapping im **Projekt**-Manifest und zeigte auf `Custom\Tests\`, einen Namensraum, den
> keine Testdatei je trug.

## Was `tests/bootstrap.php` tut — und was nicht

Es lädt **nicht** `lib/contentfly/bootstrap.php`. Der baut die komplette Anwendung auf,
verlangt eine konfigurierte Datenbank und startet eine Session — für einen Test, der eine
einzelne Klasse prüft, ist das weder nötig noch erwünscht. Mit dem Kernel-Wechsel aus Epic `009`
hat sich daran nichts geändert; nur heisst die Anwendung jetzt
`Areanet\PIM\Classes\Kernel\Application` statt `Silex\Application`.

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
