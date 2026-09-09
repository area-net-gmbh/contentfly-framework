---
id: 006-003-0002
title: Den Build aus dem committeten Stand nachweisen
status: done
depends_on: [006-003-0001]
---

# Den Build aus dem committeten Stand nachweisen

## Context
`006-003-0001` hat die Vendor-Bäume aus dem Index gelöst. Dieser Task beantwortet die Frage,
die damit offen ist: **Entsteht aus dem committeten Stand plus `composer install` dasselbe
Ergebnis wie vorher?**

Das ist nicht selbstverständlich. Der alte Baum war eingefroren und enthielt Pakete, die
Composer nie so aufgelöst hätte — `006-002-0003` hat gezeigt, dass 27 von 78 Paketen als
`source` ohne `.git` dalagen und der Baum kein gültiger Composer-Zustand war.

## Umfang

### Der Nachweis: frischer Klon
Nicht im Arbeitsverzeichnis, wo die alten Dateien noch liegen. Ein `git clone` in ein
Wegwerf-Verzeichnis hat genau das, was ein neuer Entwickler bekommt — und nichts sonst.

Dort:
```sh
composer install
```
und dann die Suite. **Das ist der Moment, in dem sich zeigt, ob `master` wieder grün ist.**

Erwartet werden die 7 bekannten Failures aus `000-000-0019` (Upload-Pfad) und `000-000-0020`
(`POST /api/schema`) — mehr nicht. Alles darüber hinaus ist ein Befund über den Ausbau.

### Die Deployment-Variante
`an_project/docs/deployment.md` nennt den Befehl, der den Baum im Artefakt erzeugt:

```sh
composer install --no-dev --optimize-autoloader
```

Auch der gehört geprüft. **Er verhält sich anders** als der normale Lauf: `--no-dev` lässt
PHPUnit weg, `--optimize-autoloader` erzeugt eine Classmap statt der PSR-4-Auflösung. Ob die
Anwendung damit bootet, ist eine eigene Frage — insbesondere für die Plugin-Annotationen, die
über `AnnotationRegistry::registerFile()` geladen werden und nicht über den Autoloader
(`006-002-0003`).

### Was zu messen ist
- Bootet die Anwendung? Console **und** HTTP.
- Läuft die Suite mit dem erwarteten Ergebnis?
- Wie lange dauert `composer install` aus dem Nichts? Das ist die Zahl, die jeder CI-Lauf ab
  jetzt zahlt — sie gehört ins Ergebnis, damit `006-005` weiss, worüber es redet.

## Abgrenzung
Keine Dokumentation — das ist `006-003-0003`. Keine Reparatur der 7 bekannten Brüche; die
haben eigene Tickets.

Stellt sich heraus, dass der Ausbau etwas kaputt macht, ist das ein **Befund** und
gegebenenfalls ein Grund, `006-003-0001` zurückzunehmen — nicht etwas, das hier nebenbei
repariert wird.

## Acceptance criteria
- [x] Ein frischer Klon plus `composer install` erzeugt einen vollständigen Baum.
- [x] Console und HTTP booten daraus.
- [x] Die Suite liefert genau die 7 bekannten Failures — keine weiteren.
- [x] `composer install --no-dev --optimize-autoloader` ist geprüft; ob die Anwendung damit
      bootet, steht im Ergebnis.
- [x] Die Dauer eines `composer install` aus dem Nichts ist gemessen und festgehalten.

## Verification
Der Klon wird **nach** dem Lauf gelöscht; was bleibt, ist das Protokoll. Es gehört wörtlich
ins Ergebnis, nicht zusammengefasst — `006-005` und Epic `009` bauen darauf auf.

Die Suite läuft mit `CI=true`, damit der Wächter aus `008-005-0002` greift und ein stiller
Übersprung auffällt.

## Ergebnis
**Der Ausbau trägt.** Ein frischer Klon des Story-Branches — 383 Dateien, kein `vendor/`, kein
`custom/vendor/` — wird mit einem `composer install` vollständig, bootet über Console und HTTP
und liefert in der Suite **genau die 7 bekannten Failures**, keinen weiteren.

Und er hat einen Bruch aufgedeckt, der nichts mit dem Ausbau zu tun hat, aber ihn blockiert:
**die Pipeline kann `composer install` gar nicht ausführen** (`000-000-0021`).

### Der Baum, den Composer erzeugt

| | Pakete | Dateien | Grösse |
|---|---|---|---|
| `composer install` (mit dev) | 77 | 7 275 | 76 MB |
| `composer install --no-dev --optimize-autoloader` | 49 | 2 694 | 22 MB |
| *zum Vergleich:* der alte committete Stand | ohne Manifest | 11 208 | — |

`custom/vendor/` **wird nicht wieder aufgebaut** — es gibt keinen Schritt, der es täte, und es
fehlt niemandem. Beide Bootstraps laden es über ein `file_exists`-Tor
(`lib/contentfly/bootstrap.php:6`, `tests/bootstrap.php:19`), das jetzt schlicht nicht greift.
Die grüne Suite belegt es. Damit ist das Erfolgskriterium „`custom/vendor` ist initial leer"
aus Epic `006` nicht nur entschieden, sondern hergestellt.

### Der Lauf, wörtlich

```
Console:  php bin/console.php list            → Exit 0, appcms:install und appcms:setup gelistet
HTTP vor der Installation:                    → HTTP 503, sauberer JSON-Envelope
          {"message":"Contentfly ist nicht installiert. Installation ausfuehren: ..."}
Installation:                                 → Exit 0, Schema und Basisdaten angelegt
HTTP nach der Installation:
  POST /api/v1/example/bootstrap              → HTTP 200, "success":true
```

Der 503 ist kein Mangel, sondern der Beleg: Autoloader, Silex und die Fehlerbehandlung stehen
bereits, bevor irgendeine Konfiguration existiert.

```
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.3.26

Time: 00:06.269, Memory: 14.00 MB

There were 7 failures:

1) Tests\Integration\Api\RouteSecurityApiTest::testEineGesicherteRouteAntwortetMitToken
2) Tests\Integration\Api\FileApiTest::testUeberschreibenErsetztDenInhaltDesZiels
3) Tests\Integration\Api\FileApiTest::testAuslieferungAntwortetMitRedirectAufDieDatei
4) Tests\Integration\Api\FileApiTest::testUploadLegtEineDateiAnUndLiefertIhreId
5) Tests\Integration\Api\FileApiTest::testUeberschreibenVerlangtGleicheDateinamen
6) Tests\Integration\Api\FileApiTest::testUploadFunktioniertUeberDenRohenFilesArrayPfad
7) Tests\Integration\Api\FileApiTest::testHochgeladeneDateiLiegtByteGleichAufDerPlatte

FAILURES!
Tests: 247, Assertions: 595, Failures: 7.
```

Die Aufteilung ist die vorhergesagte, Stück für Stück: **sechs** in `FileApiTest` — genau die
Zahl, die `000-000-0019` nennt — und **einer** in `RouteSecurityApiTest`, namentlich der Test,
den `000-000-0020` benennt. Keine Abweichung, weder nach oben noch nach unten.

**0 übersprungene Tests**, gelaufen mit `CI=true`, der Wächter aus `008-005-0002` war also
scharf. Der Postausgang der Versandfalle ist **0 Byte** — kein Lauf hat zuzustellen versucht.

### Der Deployment-Baum bootet

`--no-dev --optimize-autoloader` erzeugt eine Classmap mit **2 276 Einträgen** und lässt PHPUnit
weg. Dagegen geprüft, mit Token:

| Aufruf | Ergebnis |
|---|---|
| `php bin/console.php list` | Exit 0 |
| `POST /auth/login` | 200, Token ausgestellt |
| `GET /api/schema` | 200 |
| `POST /api/list` (`PIM\User`) | 200, ein Datensatz |
| `POST /api/v1/example/bootstrap` | 200 |
| Serverlog | keine Fehler, keine Deprecations |

Damit ist die offene Frage des Tasks beantwortet: Die **Doctrine-Annotationen werden auch unter
der optimierten Classmap gelesen** — `/api/list` auf `PIM\User` geht durch die Metadaten und
liefert den Admin-Datensatz.

**Nicht beantwortet ist die Plugin-Hälfte.** `AnnotationRegistry::registerFile()` wird laut
`lib/contentfly/bootstrap.php` für Plugin-Annotationen gebraucht — `plugins/` ist im Klon aber
leer, es gibt kein Plugin, an dem sich das zeigen liesse. Das ist eine Lücke im Nachweis, keine
bestandene Prüfung. Ebenso läuft auf dem Deployment-Baum **keine Suite**: `--no-dev` nimmt
PHPUnit mit. Was hier steht, sind Boot- und Endpunktprüfungen, nicht 247 Tests.

### Die Dauer — die Zahl, die jeder CI-Lauf zahlt

Gemessen im Pipeline-Image `php:8.3-cli`, mit leerem Composer-Cache, also so, wie die Pipeline
läuft (kein Cache konfiguriert, `GIT_DEPTH: "1"`):

| Schritt des `before_script` | Dauer |
|---|---|
| `tools/ci/install-php-extensions.sh` (pdo_mysql, gd) | 14 s |
| Entpacker und `git` nachrüsten — **der Schritt, den es noch nicht gibt** (`000-000-0021`) | 9 s |
| Composer selbst installieren | 1 s |
| `composer install --prefer-dist`, kalter Cache | **5 s** |
| **Summe, bis `vendor/` steht** | **29 s** |

Der eigentliche `composer install` ist mit 5 s der **kleinste** Posten. Was der Lauf kostet,
kostet das Herrichten des Images — und das tat er schon vorher. Für `006-005` heisst das: Ein
`composer audit --locked` hängt an einem Baum, der in fünf Sekunden dasteht; die Diskussion über
einen Composer-Cache in der Pipeline lohnt sich an dieser Stelle nicht.

Lokal auf der Entwicklungsmaschine (macOS, PHP 8.3.26): **7 s** mit kaltem, **2 s** mit warmem
Cache. Diese Zahl gehört ins Runbook, nicht die 29 s — wer lokal installiert, hat Composer und
die Extensions bereits.

### Der Befund: die Pipeline kann nicht bauen

Beim Messen im echten Image scheiterte `composer install` sofort:

```
Failed to download psr/cache from dist:
  The zip extension and unzip/7z commands are both missing, skipping.
Source fallback is disabled. Not trying alternative sources.
```

Einzeln nachgemessen, nicht aus der Meldung geschlossen: `php:8.3-cli` hat **weder** die
`zip`-Extension **noch** `unzip` **noch** `7z` **noch** `git`. Beide Wege sind damit zu:
`--prefer-dist` findet keinen Entpacker, und der Quell-Fallback bräuchte `git`. Gegengeprobt
mit einem `composer install` ohne den Schalter — dieselbe Meldung.

**Das ist kein Schaden des Ausbaus.** Der Bruch entstand mit `006-002-0004`, als der
`composer install`-Schritt in die Pipeline kam; ein fehlgeschlagenes `before_script` hätte den
Job auch mit committetem `vendor/` abgebrochen. Der Ausbau nimmt nur die letzte Ausrede weg.
Aufgefallen ist es erst hier, weil dieser Task das Image zum ersten Mal seit `008-005-0001`
wieder von Null gefahren hat.

Festgehalten in `000-000-0021`, nicht hier behoben — die Abgrenzung des Tasks ist eindeutig.
Mit nachgerüstetem `unzip` und `git` läuft der Schritt durch und erzeugt die 77 Pakete; das ist
die Messung oben.

### Zwei Dinge, die ich beim Messen selbst falsch gemacht habe

**Die falsche Login-Route.** Gegen den Deployment-Baum rief ich `POST /api/login` auf, bekam
`500 Service "api.controller" is not callable` und hielt das kurz für einen Befund über
`--optimize-autoloader`. Die Gegenprobe über alle drei Install-Varianten — auch die, die
Minuten zuvor 247 Tests gefahren hatte — lieferte denselben 500. Damit war klar, dass nicht der
Baum falsch war, sondern mein Aufruf: Die Route heisst `/auth/login` und will `alias`, nicht
`user`. Ein Befund, der in *allen* Varianten auftritt, ist keiner über eine davon.

**Der Container des Users.** `docker-compose.yml` vergibt einen festen `container_name:
contentfly-db`, und ein solcher Container existiert auf der Maschine bereits — der des
Hauptarbeitsverzeichnisses. Der Klon bekam deshalb ein
`docker-compose.override.yml` mit eigenem Namen (`contentfly-db-klon`) auf Port 3317 und ein
eigenes Volume. Der bestehende Container und seine Daten wurden nicht angefasst. Die Override
liegt im Wegwerf-Klon, nicht im Repo.

Nebenbei sichtbar geworden: **der feste `container_name` verträgt keine zweite Arbeitskopie.**
Kein eigenes Ticket wert, aber wer je zwei Klone gleichzeitig braucht, stolpert darüber.

### Was der Klon offenlässt
`GET /` antwortet mit **500** statt der im Runbook genannten 405. Das ist `000-000-0006`
(Fehlerantworten und WEB_ROOT), unverändert und hier nur wiedergesehen — der Smoke-Test-Pfad
aus dem Runbook selbst liefert sauber 200.

Der Klon bleibt bis zum Ende von `006-003-0003` stehen: Dessen Verification verlangt, das
Runbook aus genau diesem Klon nachzuspielen. Gelöscht wird er dort, samt Container und Volume.
