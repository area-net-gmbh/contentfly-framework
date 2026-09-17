<!-- PURPOSE: RUNBOOK-Vorlage für "Individual" (kopiert von /new-project nach
     an_project/docs/runbook.md). /new-project füllt {{BACKEND}} und {{FRONTEND}} aus den
     Interview-Antworten. Freie Prosa, KEIN Work-Item-Frontmatter. Erklärt einem Developer,
     wie er das Projekt nach dem Checkout lokal zum Laufen bringt. -->

# Runbook — lokales Setup (Individual)

Schritt für Schritt vom frischen Checkout zur laufenden lokalen Umgebung. Das Team ergänzt
die konkreten Befehle je Stack und ersetzt die Platzhalter unten.

## Voraussetzungen
- **Docker & Docker Compose** — für die Datenbank
- **PHP** lokal (aktuell 8.3, Zielplattform 8.5). Bewusst nicht im Container: Ein
  PHP-Container würde die Version festschreiben, die im Zuge der Migration gerade
  geändert wird.
- **Composer** — zwingend. Seit Story `006-003` liegt **kein `vendor/`-Baum mehr im Repo**;
  er entsteht bei der Installation. Getestet mit Composer 2.6.

## So startet ein neues Projekt

**Seit `011-002-0004` gefahren und als Gate festgenagelt.** Was hier steht, wiederholt
`tools/ci/bezugsweg-pruefen.sh` bei jedem Pipeline-Lauf — ein Weg, den niemand fährt, verrottet.

> **Dieser Abschnitt gilt für ein NEUES Projekt.** Ein Bestandsprojekt, das von Contentfly 1.x
> kommt, folgt `an_project/docs/migration.md`; Phase 2 dort nennt denselben Bezugsweg.

**Voraussetzung:** Lesezugriff auf `area-net-gmbh/contentfly-framework-dist` — ein Deploy Key oder
ein Konto in der Organisation. Ohne ihn scheitert `composer install` mit `Permission denied
(publickey)`, und die Meldung sagt nicht, woran es liegt.

**1. Die Dateien, mit denen ein Projekt startet.** Dieses Repository ist zugleich das Skeleton;
was ein Projekt braucht, ist die Wurzel abzüglich dessen, was nur der Entwicklung dient:

```
.htaccess  bin/  custom/  data/  index.php  plugins/  favicon.ico  robots.txt
```

**2. Ein eigenes `composer.json`** — mit dem Paket-Repository statt des `path`-Eintrags, den
dieses Entwicklungs-Repo benutzt:

```json
{
    "name": "ihre-firma/ihr-projekt",
    "type": "project",
    "repositories": [
        { "type": "vcs", "url": "git@github.com:area-net-gmbh/contentfly-framework-dist.git" }
    ],
    "require": {
        "areanet/contentfly": "^2.0",
        "vlucas/phpdotenv": "^5.6"
    },
    "config": {
        "preferred-install": { "areanet/contentfly": "source" }
    },
    "autoload": {
        "psr-4": { "Custom\\": "custom/", "Plugins\\": "plugins/" }
    }
}
```

> **`preferred-install: source` ist nicht optional**, und die Meldung ohne diese Zeile führt in die
> Irre. Composer bezieht ein Paket am liebsten als `dist`, also als Zip, und holt dieses Zip bei
> GitHub über die **REST-API**. Die kennt einen SSH-Deploy-Key nicht — bei einem privaten
> Repository antwortet sie mit
>
> ```
> https://api.github.com/repos/…/zipball/<sha>  →  404 Not Found
> ```
>
> was aussieht, als gäbe es das Paket nicht. `source` heisst *klonen statt herunterladen*, und das
> geht über SSH — also mit genau dem Zugang, den Sie ohnehin brauchen. Die Zeile betrifft nur
> dieses eine Paket; alle anderen kommen von Packagist und weiterhin als Zip.
>
> Die Alternative wäre ein **API-Token** je Entwickler (`composer config github-oauth.github.com
> …`). Möglich, aber es hängt an einem Konto statt an einem Repository — dieselbe Abwägung wie bei
> der Wahl des Deploy Keys.

> **Dieselbe Zeichenkette steht in `tools/ci/bezugsweg-pruefen.sh`** — wer eine ändert, ändert
> beide, sonst prüft das Gate einen anderen Weg als die Doku beschreibt. Und weil das Gate bei
> jedem Lauf ein frisches Projekt genau so aufsetzt, ist dieser Block nicht nur beschrieben,
> sondern nachgefahren.
>
> Bis zum Release stand hier `^2.0@RC`. Seit `v2.0.0` gesetzt ist, braucht es den Zusatz nicht
> mehr — er hätte sonst weiterhin Vorab-Versionen zugelassen, und das will ein Projekt nicht.

**3. Installieren:**

```sh
composer install
php bin/console.php appcms:install -n \
    --db-host=127.0.0.1 --db-port=3306 --db-name=<name> \
    --db-user=<benutzer> --db-pass=<passwort> \
    --db-strategy=guid --admin-password=<passwort>
```

**4. Nachsehen, dass es wirklich aus dem Paket kam:**

```sh
php -r '$l=json_decode(file_get_contents("composer.lock"),true);
        foreach($l["packages"] as $p) if($p["name"]==="areanet/contentfly")
            printf("%s aus %s\n", $p["version"], $p["source"]["url"]);'
```

Steht dort `dev-master` statt einer Version, hat Composer die Tags nicht gesehen — dann ist im
Paket ein `version`-Feld gelandet, das jeden abweichenden Tag verwirft. Der Fall ist in
`011-002-0003` beschrieben.

**Ein Update ist danach `composer update areanet/contentfly`.** Der Framework-Baum wird nie wieder
angefasst — das ist der ganze Zweck des Pakets.

## 1. Abhängigkeiten installieren

**Der erste Schritt, vor allem anderen.** Ein frischer Checkout ist ohne ihn nicht lauffähig —
`vendor/` liegt seit Story `006-003` nicht mehr im Repo, sondern entsteht hier:

```sh
composer install
```

Das ergibt 77 Pakete in `vendor/` und dauert **rund 7 Sekunden** mit leerem Composer-Cache, 2
Sekunden mit gefülltem (gemessen in `006-003-0002`, macOS, PHP 8.3). Wer nichts sieht, wartet
also nicht lange — bleibt es länger stehen, hängt es an der Netzverbindung, nicht am Projekt.

> **Wenn dieser Schritt fehlt, meldet sich das Projekt nicht mit seinem Grund**, sondern mit
> fehlenden Klassen oder einem fehlenden Autoloader. Wer eine solche Meldung sieht, prüft
> zuerst, ob `vendor/` existiert.

`custom/vendor/` wird **nicht** gebaut und wird auch nicht gebraucht — beide Bootstraps laden es
nur, falls es da ist.

Für ein Deployment-Artefakt statt einer Arbeitskopie:

```sh
composer install --no-dev --optimize-autoloader   # 49 Pakete, ohne PHPUnit
```

Damit lässt sich die Suite **nicht** fahren; PHPUnit liegt in `require-dev`.

## 2. Datenbank hochfahren

```sh
docker compose up -d
docker compose ps            # wartet, bis der Dienst "healthy" meldet
```

Startet einen MySQL 8.0 unter dem Namen `contentfly-db`:

| | Standard | überschreibbar mit |
|---|---|---|
| Port | **3307** | `CONTENTFLY_DB_PORT` |
| Datenbank | `contentfly` | `CONTENTFLY_DB_NAME` |
| Benutzer / Passwort | `contentfly` / `contentfly` | `CONTENTFLY_DB_USER` · `CONTENTFLY_DB_PASSWORD` |
| root-Passwort | `root` | `CONTENTFLY_DB_ROOT_PASSWORD` |

Der Port steht bewusst **nicht** auf 3306 — dort läuft auf Entwicklungsmaschinen meist
schon eine andere Datenbank. Zeichensatz und Kollation sind auf `utf8mb3` /
`utf8mb3_unicode_ci` gesetzt, passend zu `Config::DB_CHARSET` und `DB_COLLATE`.

Verbindung prüfen:

```sh
php -r '$p = new PDO("mysql:host=127.0.0.1;port=3307;dbname=contentfly", "contentfly", "contentfly");
        echo $p->query("SELECT VERSION()")->fetchColumn(), "\n";'
```

**Daten wegwerfen und neu anfangen** — das benannte Volume überlebt `down`, deshalb braucht
es `-v`:

```sh
docker compose down -v       # Container UND Datenvolume entfernen
docker compose up -d         # frische, leere Datenbank
```

**Herunterfahren ohne Datenverlust:** `docker compose down`

## 3. Backend installieren

```sh
php bin/console.php appcms:install \
    --db-host=127.0.0.1 --db-port=3307 --db-name=contentfly \
    --db-user=contentfly --db-pass=contentfly \
    --dry-run                                    # erst prüfen, schreibt nichts

php bin/console.php appcms:install \
    --db-host=127.0.0.1 --db-port=3307 --db-name=contentfly \
    --db-user=contentfly --db-pass=contentfly    # dann wirklich installieren
```

**`--db-port=3307` ist nicht optional.** Der Command hat den Default `3306`, `docker-compose.yml`
veröffentlicht aber `3307`. Ohne den Schalter endet der Lauf mit
`SQLSTATE[HY000] [2002] Connection refused` — was wie ein nicht laufender Container aussieht und
keiner ist. Aufgefallen beim Nachspielen dieser Anleitung in `006-003-0003`; `tests/README.md`
hatte den Schalter von Anfang an. Das `--dry-run` fängt es ab, bevor etwas geschrieben wird.

Der Command schreibt `custom/config.php`, legt das Schema an und erzeugt die Basisdaten
(Benutzer `admin`). Alle Optionen gibt es auch als Umgebungsvariable (`APPCMS_DB_HOST` …),
und `--admin-password` setzt ein echtes Passwort statt des Standards.

> **Der Hinweis, dass dies noch nicht durchführbar sei, ist entfallen.** `appcms:install`
> liegt seit Story `012-002` im Hauptzweig, und `custom/config.php` trägt seit
> `000-000-0002` wieder die `$SET_*`-Platzhalter. Die Installation läuft.

### Danach: die Vorlage wiederherstellen

`appcms:install` schreibt Host, Benutzer und Passwort **in `custom/config.php` — eine Datei,
die versioniert im Repo liegt**, weil sie die Vorlage ist. Nach der Installation meldet
`git status` sie als geändert, und ein `git add -A` genügt, damit die Zugangsdaten in der
Historie landen. Zweiter Schaden: Eine committete Konfiguration macht den nächsten Checkout
uninstallierbar — `bootstrap.php` hält das System dann für bereits eingerichtet.

Vor dem Commit deshalb:

```sh
git checkout HEAD -- custom/config.php
```

**Das `HEAD` ist wichtig.** Ist die Datei bereits gestagt, holt `git checkout -- <pfad>` sie
aus dem *Index* zurück und schreibt die installierte Fassung erneut in den Arbeitsbaum — es
sieht aus wie eine Wiederherstellung und ist keine.

### Den Schutz einmalig aktivieren

Damit man nicht daran denken muss, liegt ein `pre-commit`-Hook im Repo. Git-Hooks lassen sich
nicht versionieren, deshalb ist er einmalig zu aktivieren:

```sh
git config core.hooksPath tools/hooks
```

Er prüft die **gestagte** Fassung: Wer lokal installiert hat und etwas anderes committet, wird
nicht aufgehalten — nur wer die Konfiguration wirklich mit einpackt. Dieselbe Prüfung läuft in
der Pipeline als Job `check:template-config`; beide rufen `tools/check-template-config.sh` auf
und melden deshalb dasselbe.

> `core.hooksPath` ersetzt `.git/hooks` vollständig. Wer dort eigene Hooks liegen hat, nimmt
> sie mit nach `tools/hooks/` oder kopiert stattdessen nur diese eine Datei nach
> `.git/hooks/pre-commit`.

Die Verzeichnisse unter `data/` (`files`, `cache`, `temp`, `import`) liegen im Repo, weil der
Installer in sie schreibt; ihr Inhalt ist ignoriert.

## 4. Frontend installieren
Entfällt — dieses Projekt hat kein Frontend (reines Backend/API).

## 4a. Smoke-Test ohne Datenbank

Der aktuelle Stand bootet ohne laufende Datenbank, solange die aufgerufene Route keine
Daten anfasst. Damit lässt sich nach jedem Eingriff in 30 Sekunden prüfen, ob Framework
und Vorlage überhaupt noch hochkommen:

```sh
# 1. Console — bootet den Kernel und listet die Commands
php bin/console.php list

# 2. HTTP — eingebauter PHP-Server, index.php als Router
php -S 127.0.0.1:8123 index.php &
curl -s -X POST http://127.0.0.1:8123/api/v1/example/bootstrap
kill %1
```

Erwartet wird der Standard-Envelope mit `"success":true` und einem Zeitstempel im
API-Format. Kommt stattdessen eine HTML-Fehlerseite, steht die Ursache in deren
`exception-message` — meist eine Klasse, die die Vorlage referenziert, aber nicht mitbringt.

**Auf `/` und auf unbekannten Pfaden kommt `HTTP 405` mit einer JSON-Antwort.** Der
OPTIONS-Catch-All (`{anything}`) in `lib/contentfly/bootstrap-web.php` fängt jeden Pfad ab,
sodass GET dort „Method Not Allowed" bedeutet. Das ist erwartet und **kein Grund, den
Smoke-Test für gescheitert zu halten**; massgeblich ist die Antwort auf
`/api/v1/example/bootstrap` oben.

Bis `000-000-0006` kam an dieser Stelle `500`, und mit `tests/router.php` und `APP_DEBUG=0` —
so fährt die Suite — sogar ein `302` auf `/`, also eine Umleitung auf sich selbst. Beides ist
behoben: Der Statuscode kommt jetzt aus `getStatusCode()`, wenn die Ausnahme keinen eigenen
trägt, und die Umleitung auf `/` ist entfallen — sie stammte aus der Zeit, als dort die
PIM-Oberfläche lag.

## 4b. Tests ausführen

```sh
./vendor/bin/phpunit                    # beide Suiten
./vendor/bin/phpunit --testsuite unit   # ohne Datenbank, muss immer grün sein
```

`tests/Unit` läuft ohne Container, `tests/Integration` braucht die Datenbank aus Schritt 2 und
eine durchgeführte Installation. Details und der Grund für die Trennung: `tests/README.md`.

Für Abdeckung wird ein Treiber gebraucht (Xdebug oder PCOV) und der Schalter `--coverage-text`.
Die Konfiguration fordert bewusst keinen Bericht bei jedem Lauf an — sonst endet die Suite ohne
installierten Treiber mit Exit-Code 1, obwohl jeder Test grün ist.

## 5. Zugriff
<!-- URLs/Ports: Backend-API, DB, Mailhog … -->
- Backend-API: http://localhost:8000

## 6. API-Doku neu generieren
<!-- Befehl, der die OpenAPI/Swagger-Doku aktualisiert — Details in an_project/docs/dev-guide.md. -->

## Troubleshooting
<!-- Häufige Stolpersteine: Ports belegt, DB-Verbindung, Cache leeren (bin/console cache:clear). -->
