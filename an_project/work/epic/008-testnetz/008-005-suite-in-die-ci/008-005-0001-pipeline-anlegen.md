---
id: 008-005-0001
title: Die Pipeline anlegen: Datenbank, Installation und Testserver
status: review
depends_on: []
---

# Die Pipeline anlegen: Datenbank, Installation und Testserver

## Context
Das Repo hat keine Pipeline-Definition. Die Suite läuft heute nur, wenn jemand von Hand eine
Datenbank hochfährt, installiert und einen Testserver startet — also praktisch nur auf einer
Maschine. Ein Testnetz, das niemand fährt, hilft beim Kernel-Umbau nicht.

Dieser Task legt die Pipeline an, in die sich `008-005-0002` (Wächter), `008-005-0003`
(`config.php`-Gate) und später `006-005` (`composer audit`, Deprecations-Gate) einfügen.

## Umfang

### Die Plattform
**GitLab CI**, `.gitlab-ci.yml` im Repo-Root. Der Remote ist `gitlab.in.area-net.de`; damit
ist die offene Frage der Story beantwortet.

> **Annahme, die vor dem ersten Lauf zu prüfen ist:** Es steht ein Runner mit
> **Docker-Executor** bereit. Ohne den funktioniert weder das `image:` noch das `services:`
> dieser Definition. Ist nur ein Shell-Runner verfügbar, ändert das den Zuschnitt erheblich —
> dann muss der Job Datenbank und PHP selbst mitbringen.

### Die PHP-Jobs
- **PHP 8.3 — pflicht.** Der Stand, gegen den heute alles grün ist.
- **PHP 8.4 — `allow_failure: true`.** Frühwarnung, was der Silex-Stand beim Sprung bricht,
  ohne die Pipeline rot zu färben. Zielplattform ist 8.5; die Versionen wandern mit Epic `006`
  und `009` mit.

Basis ist `php:<version>-cli`. Nachzuinstallieren sind:

- **`pdo_mysql`** — ohne das kommt keine Verbindung zustande.
- **`gd`** — heute von keinem Test gebraucht, aber `lib/contentfly/Classes/File/Processing/Image.php`
  benutzt es für Thumbnails. Ohne die Extension scheitert die erste Erweiterung der Suite am
  Image statt am Code, und der Grund wäre schwer zu finden.

`mbstring`, `ctype`, `json`, `openssl`, `filter`, `hash`, `phar` und `tokenizer` bringt das
Image mit.

### Kein `composer install`
`vendor/` und `custom/vendor/` liegen **committed** im Repo — knapp 6000 Dateien allein unter
`custom/vendor/`. Die Pipeline ruft deshalb direkt `./custom/vendor/bin/phpunit` auf.

Das ist kein Ideal, sondern der heutige Stand; Epic `006` räumt ihn ab. Der Aufruf ist
deshalb so zu schreiben, dass die Umstellung auf `./vendor/bin/phpunit` **ein Einzeiler**
bleibt — am besten als Variable an einer Stelle, nicht verstreut über mehrere Jobs.

### Die Datenbank
MySQL 8.0 als `services:`-Eintrag, mit denselben Flags wie `docker-compose.yml`:

```
--character-set-server=utf8mb3 --collation-server=utf8mb3_unicode_ci
```

**Das ist kein Detail.** Zeichensatz und Kollation müssen zu `Config::DB_CHARSET` und
`DB_COLLATE` passen, sonst legt die Installation Tabellen an, die nicht zum erwarteten Schema
passen — und die Fehler tauchen erst weit später auf. Die Umstellung auf `utf8mb4` ist eine
Datenmodell-Entscheidung und gehört zu Epic `010`, nicht hierher.

Im Service ist der Port 3306 (kein Port-Mapping wie lokal); der Host ist der Service-Name.
Die Testkonfiguration muss das über `CONTENTFLY_TEST_DB_HOST` / `_PORT` mitbekommen.

### Die Installation
```sh
php bin/console.php appcms:install -n \
    --db-host=… --db-port=… --db-name=… --db-user=… --db-pass=… \
    --db-strategy=guid --admin-password=…
```

Der Command nimmt seit `012-002` alle Werte als Optionen und kennt `-n` für „keine
Rückfragen"; jede Option hat zusätzlich eine `APPCMS_*`-Umgebungsvariante. Das Admin-Passwort
gehört als CI-Variable hinterlegt, nicht in die YAML.

Auf die Datenbank ist zu **warten**, bevor installiert wird — ein Service ist gestartet, lange
bevor er antwortet. `docker-compose.yml` löst das mit einem Healthcheck; im Job braucht es
eine gleichwertige Warteschleife.

### Der Testserver
```sh
APP_ENV=production APP_DEBUG=0 \
  php -d sendmail_path=<Fangskript> -S 127.0.0.1:8145 tests/router.php &
```

Drei Dinge, die keine Geschmacksfragen sind:

- **`tests/router.php` ist nicht optional.** Ohne ihn schickt der eingebaute Server *jede*
  Anfrage durch `index.php`, auch die für eine Datei, die auf der Platte liegt. Die
  Dateiauslieferung hängt genau daran, und die Tests messen sonst etwas anderes als die
  Produktion.
- **`APP_DEBUG=0`**, sonst überdeckt der Debug-Exception-Handler die Antworten der Anwendung
  (`000-000-0006`).
- **Die Versandfalle** aus `008-004-0004`: `sendmail_path` zeigt auf ein Fangskript, und
  `CONTENTFLY_TEST_MAIL_TRAP` zeigt auf dessen Verzeichnis. Ohne beides überspringen sich drei
  Tests still — und sobald `/api/mail` repariert ist (`000-000-0016`), verschickt ein Lauf
  ohne Falle echte Mail.

Auch hier gilt: auf den Server warten, bevor die Suite startet.

### Beide Suiten
`unit` läuft ohne Umgebung und muss auf jedem Checkout grün sein; `integration` braucht die
oben aufgebaute Umgebung. Der Aufruf ohne `--testsuite` fährt beide — so bleibt es bei einem
Kommando.

## Abgrenzung
- Der Wächter gegen stille Übersprünge ist `008-005-0002`. Diese Definition muss ihn nur
  **ermöglichen**, indem sie die nötigen Variablen setzt.
- Das Gate auf `custom/config.php` ist `008-005-0003`.
- `composer audit --locked` und das „0 Deprecations"-Gate kommen mit `006-005` in dieselbe
  Pipeline. Die Stage-Struktur ist so zu wählen, dass sie dort Platz finden, ohne umgebaut zu
  werden.
- Keine Coverage-Berichte: `phpunit.xml.dist` begründet ausführlich, warum sie nicht fest
  verdrahtet sind (fehlt Xdebug oder PCOV, endet die Suite mit Exit-Code 1, obwohl jeder Test
  grün ist).

## Acceptance criteria
- [x] `.gitlab-ci.yml` liegt im Repo-Root und definiert die Jobs für PHP 8.3 (pflicht) und
      PHP 8.4 (`allow_failure: true`).
- [x] Der Job installiert `pdo_mysql` und `gd` und startet MySQL 8.0 als Service mit
      `utf8mb3` / `utf8mb3_unicode_ci`.
- [x] Der Job wartet nachweislich auf die Datenbank, bevor er installiert, und auf den Server,
      bevor er testet — keine festen `sleep`-Werte, sondern eine Prüfschleife.
- [x] Die Installation läuft ohne Rückfrage durch; das Admin-Passwort kommt aus einer
      CI-Variablen, nicht aus der YAML.
- [x] Der Testserver läuft über `tests/router.php` mit `APP_DEBUG=0` und einer eingerichteten
      Versandfalle; `CONTENTFLY_TEST_MAIL_TRAP` ist gesetzt.
- [x] Beide Suiten laufen im selben Job und sind grün.
- [x] Der PHPUnit-Pfad steht an **einer** Stelle, sodass die Umstellung durch Epic `006` ein
      Einzeiler ist.
- [x] Die Runner-Annahme (Docker-Executor) ist im Kopf der Datei vermerkt.

## Verification
Die Pipeline kann in dieser Sitzung nicht gestartet werden — kein Push, kein erreichbarer
Runner. Der Nachweis erfolgt deshalb **lokal in Docker**: Dieselben Befehle in derselben
Reihenfolge in einem `php:8.3-cli`-Container gegen einen MySQL-8.0-Container, mit denselben
Flags und Umgebungsvariablen wie in der YAML. Festzuhalten ist die Ausgabe des Laufs.

Zusätzlich die YAML-Syntax prüfen. Der erste echte Pipeline-Lauf bleibt ein Schritt, den
jemand mit Push-Rechten macht — das ist im Ergebnis so zu vermerken und **nicht** als
erledigt auszugeben.

## Ergebnis
`.gitlab-ci.yml` plus `tools/ci/install-php-extensions.sh` und
`tools/ci/prepare-test-environment.sh`. Zwei Jobs: `test:php8.3` (pflicht) und `test:php8.4`
(`allow_failure`).

### Der Nachweis
Vollständiger Joblauf **von Null** in Docker — frischer Klon, frische Datenbank, dieselben
Befehle und Variablen wie in der YAML:

```
before_script  install-php-extensions.sh    → PHP 8.3.33 mit pdo_mysql, gd, …
               prepare-test-environment.sh  → DB nach 0s, Installation, Falle, Server nach 1s
script         ./custom/vendor/bin/phpunit  → OK (232 tests, 564 assertions)
after_script   Postausgang der Versandfalle → 0 Bytes
Job-Exit                                    → 0
```

**PHP 8.4 ebenfalls grün** (232/232), mit einer einzigen Deprecation aus Silex selbst:
`Silex\Application::run(): Implicitly marking parameter $request as nullable`. Der
`allow_failure`-Schutz bleibt trotzdem richtig — er fängt künftige Brüche ab —, aber der
Silex-Stand läuft heute auf 8.4. Das ist eine bessere Ausgangslage für Epic `006`, als der
Story-Text annahm.

### Der Befund, der den Lauf zuerst rot machte
Der erste CI-Lauf hatte **sechs Fehler, die lokal grün waren** — dreimal
`WritePermissionApiTest`, zweimal `testNurPostIstErlaubt`, einmal
`SystemControllerApiTest::testEineFehlendeMethodeEndetEbenfallsInEinemFehler`. Alle mit
demselben Muster: `200` statt `405` oder `500`.

Die Ursache ist keine CI-Eigenheit, sondern eine echte Eigenschaft der Anwendung:

> PHP schreibt eine Deprecation direkt in den Antwortstrom. Passiert das, bevor Silex den
> Statuscode setzt, sind die Header schon unterwegs — und die Antwort trägt `200`, obwohl die
> Anwendung `405` oder `500` meint.

Mit `display_errors=Off` laufen alle 232 durch. Das ist zugleich die **Produktions**einstellung:
Eine Instanz, die Deprecations ausliefert, verrät Dateipfade an jeden Aufrufer. Dass
`bootstrap.php` sie bei `APP_DEBUG=0` **nicht erzwingt** — es setzt `display_errors` und
`error_reporting` nur im Debug-Zweig —, ist als `000-000-0018` festgehalten.

`log_errors=On` kommt dazu, damit die Deprecations nicht verschwinden, sondern im Serverlog
stehen. Das ist die Quelle, aus der das „0 Deprecations"-Gate aus `006-005` liest.

`tests/README.md` ist auf denselben Stand gebracht — sonst wiche die lokale Anleitung von der
Pipeline ab und der nächste liefe in dieselbe Wand.

### Was offen bleibt
- **Der erste echte Pipeline-Lauf.** Er braucht Push-Rechte und einen Runner; beides steht
  hier nicht zur Verfügung. Der Nachweis oben ist ein Nachspiel, kein Pipeline-Lauf.
- **Die Runner-Annahme.** Ob ein Docker-Executor bereitsteht, ist ungeklärt und im Kopf der
  `.gitlab-ci.yml` vermerkt. Steht nur ein Shell-Runner zur Verfügung, ist der Zuschnitt neu
  zu machen.
- `CONTENTFLY_TEST_ADMIN_PASS` muss in Settings → CI/CD → Variables hinterlegt werden, bevor
  die Pipeline zum ersten Mal läuft. Sie steht bewusst nicht in der YAML.
