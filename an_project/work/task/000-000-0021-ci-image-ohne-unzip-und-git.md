---
id: 000-000-0021
title: Das CI-Image kann composer install nicht ausführen
status: review
depends_on: [006-003-0002]
---

# Das CI-Image kann composer install nicht ausführen

## Context
Gefunden mit `006-003-0002` beim Nachmessen der Build-Dauer im Pipeline-Image. Der Befund ist
nicht die Dauer, sondern dass der Schritt überhaupt nicht läuft:

```
Failed to download psr/cache from dist:
  The zip extension and unzip/7z commands are both missing, skipping.
Source fallback is disabled. Not trying alternative sources.
```

`php:8.3-cli` bringt **weder** die `zip`-Extension **noch** `unzip`, `7z` **noch** `git` mit —
alle vier einzeln nachgemessen, alle vier fehlen. `tools/ci/install-php-extensions.sh` baut nur
`pdo_mysql` und `gd`; die Datei nennt in ihrem Kopf ausdrücklich, was das Image mitbringe, und
`zip` steht dort nicht.

Damit scheitert `composer install` in **beiden** Ausprägungen:

| Aufruf | Ergebnis |
|---|---|
| `composer install --prefer-dist` (so steht es in `.gitlab-ci.yml`) | scheitert — kein Entpacker |
| `composer install` ohne den Schalter | scheitert ebenso — der Quell-Fallback braucht `git`, und das fehlt auch |

**Der Bruch ist älter als `006-003`.** Er entstand mit `006-002-0004`, als der
`composer install`-Schritt in `.gitlab-ci.yml` kam. Solange `vendor/` noch committet war, wäre
der Job trotzdem rot gewesen — ein fehlgeschlagenes `before_script` bricht ab, der committete
Baum hätte ihn nicht gerettet. Aufgefallen ist es erst jetzt, weil `006-003-0002` das Image
zum ersten Mal seit `008-005-0001` wieder von Null gefahren hat.

## Umfang
`tools/ci/install-php-extensions.sh` ist die Stelle: Sie rüstet das Basis-Image auf das aus,
was das Framework braucht — und ein Entpacker gehört dazu, sobald der Baum im Job entsteht.

Zu entscheiden ist, welcher Weg genommen wird:

- **`unzip` als Systempaket** — der billigere Weg. Composer benutzt das Kommando, die
  PHP-Extension braucht es dafür nicht.
- **`zip` als PHP-Extension** (`libzip-dev` + `docker-php-ext-install zip`) — teurer im Build,
  aber unabhängig von einem Systemkommando.

`git` ist die zweite Frage: Ohne es gibt es keinen Quell-Fallback. Solange jedes Paket des
Locks als `dist` verfügbar ist, wird er nicht gebraucht — aber genau darauf hat sich der
alte Vendor-Baum schon einmal verlassen und daneben gelegen (`006-001-0003`: 27 von 78 Paketen
lagen als `source` ohne `.git`).

Der Kopfkommentar der Datei zählt auf, was das Image mitbringt. Er ist Teil der Änderung, nicht
Beiwerk — er ist heute falsch.

## Abgrenzung
Keine Änderung an `.gitlab-ci.yml` selbst; der Aufruf dort stimmt. Keine Änderung an den
Constraints in `composer.json`. Kein `composer audit` — das ist `006-005`.

## Acceptance criteria
- [x] `tools/ci/install-php-extensions.sh` stellt einen Entpacker bereit; die Wahl zwischen
      `unzip` und der `zip`-Extension ist begründet.
- [x] Entschieden und begründet, ob `git` mit ins Image gehört.
- [x] Der Kopfkommentar der Datei nennt den neuen Stand — er behauptet heute, das Image bringe
      alles Nötige ausser `pdo_mysql` und `gd` mit.
- [x] `composer install --no-interaction --no-progress --prefer-dist` läuft im Image durch und
      erzeugt die 77 Pakete des Locks.

## Verification
Der vollständige `before_script`-Ablauf lokal in Docker nachgespielt, mit demselben Image und
denselben Aufrufen wie in `.gitlab-ci.yml` — so wie `008-005-0001` es vorgemacht hat:

```sh
docker run --rm -v "$PWD":/src:ro php:8.3-cli sh -c '
  sh /src/tools/ci/install-php-extensions.sh
  php -r "copy(\"https://getcomposer.org/installer\",\"/tmp/composer-setup.php\");"
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
  mkdir -p /build && cp /src/composer.json /src/composer.lock /build/ && cd /build
  composer install --no-interaction --no-progress --prefer-dist'
```

Danach derselbe Lauf im Image `php:8.4-cli` — der zweite Job der Pipeline benutzt es.

## Ergebnis
**Die Pipeline kann wieder bauen.** Eine Zeile — `unzip` in
`tools/ci/install-php-extensions.sh` — und `composer install` läuft im Image durch: 77 Pakete,
PHPUnit vorhanden, keine einzige Meldung über einen Quell-Fallback.

**Sie ist damit nicht grün.** Der Job läuft jetzt bis zur Suite und endet mit Exit 1 wegen der
7 bekannten Failures aus `000-000-0019` und `000-000-0020`. Das ist der Unterschied zwischen
„kann nicht bauen" und „baut und findet die Defekte, die wir kennen" — und mehr war hier auch
nicht zu holen.

### Die Wahl: `unzip`, nicht die `zip`-Extension
Beide Varianten im Image gemessen, je zwei Läufe, kalter Cache:

| | Einrichtung | Grösse | `composer install` |
|---|---|---|---|
| **`unzip` (apt)** | 5 s · 5 s | 495 KB | 5 s · 6 s |
| `zip`-Extension (`libzip-dev` + Build) | 13 s · 11 s | — | 12 s · 6 s |

Die Extension kostet gut das Doppelte an Einrichtung, ohne beim Installieren schneller zu sein.
Der 12-Sekunden-Ausreisser im ersten Lauf war Rauschen — der zweite lag bei 6 s, wie `unzip`.

Sie wäre nur dann die richtige Wahl, wenn die **Anwendung** ZipArchive benutzte. Nachgesehen,
nicht angenommen: kein `ZipArchive` in `lib/`, `custom/`, `bin/`, `tests/` oder `tools/`, und
**kein `ext-zip`** in `composer.lock` — geprüft über die Anforderungen aller 77 Pakete. Die 13
verlangten Erweiterungen sind `ctype`, `dom`, `filter`, `hash`, `iconv`, `json`, `libxml`,
`mbstring`, `pcre`, `pdo`, `phar`, `tokenizer` und `xmlwriter`; `zip` ist nicht darunter.

### `git` kommt bewusst nicht mit
| | Grösse installiert | zusätzliche Pakete | Einrichtung |
|---|---|---|---|
| `unzip` | 495 KB | 0 | 1 s |
| `git` | **49 556 KB** | 9 | 6 s |

Das Hundertfache, für eine Fähigkeit, die niemand anfordert. Belegt statt vermutet: Alle 77
Pakete kommen als `dist`, der Lauf erzeugt **null** Meldungen zu „Source fallback" oder „git was
not found", und kein Manifest hat eine VCS-`repositories`-Quelle. Das Auschecken des Repos ist
Sache des Runners, nicht dieses Images — die Pipeline setzt im Kopf ihrer eigenen Datei einen
Docker-Executor voraus.

Die Bedingung, unter der das kippt, steht als *Nachrüsten, wenn* im Skript: ein Paket, das nur
als `source` verfügbar ist, oder eine VCS-Quelle in einem Manifest. Beides meldet sich mit genau
den Zeichenketten, nach denen hier gesucht wurde — dann ist die Zeile die Antwort und nicht ein
neuer Befund.

### Der Kopfkommentar
Er behauptete, dem Image fehlten nur `pdo_mysql` und `gd`. Jetzt nennt er `unzip` als drittes,
ausdrücklich als Werkzeug und nicht als PHP-Erweiterung, samt der Fehlermeldung, an der es
aufgefallen ist, den Messwerten beider Varianten und der git-Entscheidung. Die Schlusszeile gibt
die `unzip`-Version mit aus — wer das Skript laufen sieht, sieht auch, dass der Entpacker da ist.

### Verification: der ganze Job, nicht nur der Schritt
Der Task verlangte den `before_script`-Ablauf. Gefahren wurde der **vollständige Job** samt
MySQL-Service in einem eigenen Docker-Netz — dieselben Images, dieselben Aufrufe, dieselben
Variablen wie in `.gitlab-ci.yml`, gegen einen `git archive HEAD` plus dieser einen Änderung:

| | `php:8.3-cli` | `php:8.4-cli` |
|---|---|---|
| `install-php-extensions.sh` | 19 s | 16 s |
| Composer selbst installieren | 3 s | 2 s |
| `composer install --prefer-dist` | 8 s | 7 s |
| Pakete | **77** | **77** |
| `vendor/bin/phpunit` vorhanden | ja | ja |
| Meldungen zu git/source | **0** | **0** |
| `prepare-test-environment.sh` | durchgelaufen | durchgelaufen |
| Suite | 249 Tests / 598 Assertions, 7 Failures | dieselben |
| übersprungen | **0** | **0** |
| Postausgang der Versandfalle | leer | leer |
| Job-Exit | 1 (die 7 bekannten Failures) | 1, `allow_failure` |

Der `before_script` steht damit bei rund **30 s** auf 8.3 und **25 s** auf 8.4 — der
`composer install` ist mit 7–8 s weiterhin der kleinste Posten darin.

### Ein Nebenbefund für `006-005`
Auf PHP 8.4 protokolliert der Testserver **zwei** Deprecations, nicht eine:

```
Deprecated: Silex\Application::run(): Implicitly marking parameter $request as nullable …
Deprecated: Areanet\PIM\Classes\Config::__construct(): Implicitly marking parameter $config as nullable …
```

`008-005-0001` hielt fest, es sei „eine einzige Deprecation aus Silex selbst". Die zweite liegt
im **eigenen Code**. Kein eigenes Ticket — das „0 Deprecations"-Gate ist Story `006-005` und der
Sprung auf 8.5 ist Epic `009`; beide haben das im Auftrag. Festgehalten ist es hier, damit
`006-005` nicht mit der Zahl 1 plant.

### Was das Nachspielen nicht beweist
Es lief in lokalem Docker, nicht auf einem GitLab-Runner. Ungeprüft bleibt damit, ob der Runner
das Repo mit einem Helper-Image auscheckt — die Annahme, aus der die git-Entscheidung folgt.
Sie steht im Kopf von `.gitlab-ci.yml` schon als ausdrückliche Voraussetzung („Es steht ein
Runner mit DOCKER-EXECUTOR bereit"); der erste echte Lauf ist ihre Probe. Schlägt der Checkout
fehl, ist `git` eine Zeile im selben Skript.
