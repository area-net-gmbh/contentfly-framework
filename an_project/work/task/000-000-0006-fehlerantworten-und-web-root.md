---
id: 000-000-0006
title: Fehlerantworten und WEB_ROOT — 404 wird zu 500, Redirects sind umgebungsabhängig
status: review
depends_on: []
---

# Fehlerantworten und WEB_ROOT — 404 wird zu 500, Redirects sind umgebungsabhängig

## Context
Bei den Charakterisierungstests aus `012-003-0003` sind zwei Defekte in der Antwort-Behandlung
aufgefallen. Beide sind heute festgenagelt (die Tests halten das *tatsächliche* Verhalten fest),
beide gehören behoben — und beide berührt Epic `009` ohnehin, wenn die Fehlerbehandlung auf
Symfony-Listener wandert.

### 1. Eine unbekannte Datei-ID endet mit 500 statt 404

`bootstrap-web.php` sieht für `FileNotFoundException` ausdrücklich einen 404 vor:

```php
if($e instanceof FileNotFoundException){
    return new Response($e->getMessage(), 404, array('X-Status-Code' => 404));
}
```

Tatsächlich kommt **500** heraus — auch mit `APP_DEBUG=0`. Der zuvor registrierte
`Symfony\Component\Debug\ExceptionHandler` fängt die Ausnahme ab, bevor der `$app->error()`-Handler
sie sieht. Der Effekt: Ein normaler „nicht gefunden"-Fall sieht für jeden Client wie ein
Serverfehler aus — inklusive Monitoring und Fehlerraten.

### 1b. Ein Zugriff ohne Token endet ebenfalls mit 500 statt 401

Dieselbe Ursache, anderer Fall: `BaseControllerProvider` wirft bei fehlendem oder ungültigem
Token eine `ContentflyException` bzw. `AccessDeniedHttpException`. Auch die kommt beim Client
als **500** an — nachgewiesen in `tests/Integration/Api/AuthApiTest.php`.

Ein fehlender Token ist der häufigste Normalfall einer API überhaupt. Dass er als Serverfehler
erscheint, macht jede Fehlerauswertung auf Clientseite unmöglich und verzerrt das Monitoring.

### 2. `WEB_ROOT` kommt aus `$_SERVER['PHP_SELF']`

`bootstrap-web.php` leitet `Config::WEB_ROOT` aus `dirname($_SERVER['PHP_SELF'])` ab.
`FileController::getAction()` baut damit einen **relativen** Redirect auf `data/files/…`.

Unter Apache mit der `.htaccess`-Rewrite stimmt das. Unter dem eingebauten PHP-Server ergibt
`PHP_SELF` etwas anderes, und der Redirect zeigt ins Leere — beobachtet:
`Location: /index.php/file/get/data/files/…`.

Folge für die Tests: Die Auslieferung lässt sich nicht end-to-end prüfen, nur der Redirect
selbst. Für Epic `008` ist das zu wenig — ein Testnetz, das die Auslieferung nicht abdeckt,
lässt beim Kernel-Wechsel genau die Funktion ungeprüft, die jeder App-Client braucht.

## Acceptance criteria
- [x] Eine unbekannte Datei-ID beantwortet die API mit **404**, unabhängig von `APP_DEBUG`.
- [x] Ein Zugriff ohne oder mit ungültigem Token beantwortet die API mit **401**.
- [x] Der Debug-Exception-Handler übernimmt nicht mehr die Fälle, für die die Anwendung eine
      eigene Antwort vorsieht.
- [x] Der Basispfad kommt aus der Konfiguration, nicht aus `PHP_SELF` — oder der Redirect wird
      absolut aufgebaut, sodass er unabhängig vom Webserver stimmt.
- [x] Die Auslieferung ist unter dem Testserver end-to-end prüfbar; die entsprechenden
      Erwartungen in `tests/Integration/Api/FileApiTest.php` sind nachgezogen (heute halten sie
      301 und 500 fest).
- [x] Beide Änderungen sind als Breaking Change für Epic `007` notiert — ein Client, der sich
      auf 500 bei unbekannter ID verlässt, sieht künftig 404.

## Verification
Gegen eine installierte Instanz: Eine unbekannte ID liefert 404. Ein Abruf einer vorhandenen
Datei liefert deren Inhalt — nicht nur einen Redirect, dem niemand folgen kann. Die Integrationssuite
läuft grün, nachdem die Erwartungen angepasst wurden.

## Ergebnis

**Der Task hat die Ursache falsch benannt, und das hat sich beim Messen herausgestellt.** Er
sagt, der Debug-Exception-Handler fange die Ausnahmen ab. Die beiden ersten Punkte — unbekannte
Datei-Id mit 404, Zugriff ohne Token mit 401 — waren beim Beginn der Arbeit **bereits erfüllt**,
behoben durch den Stack-Wechsel in `006-002-0003`; die Tests halten sie seither so fest. Was
blieb, war ein anderer Defekt mit demselben Symptom.

### Die eigentliche Ursache: `Api::getSingle()` gab eine `JsonResponse` zurück

Aufgefallen beim Messen für `000-000-0009`. Ich hatte 404 erwartet, weil `doUpdate()` bei
unbekannter Id eine `ContentflyException` mit diesem Code wirft, und 500 gemessen. Der Grund
stand im Serverlog:

```
Areanet\PIM\Classes\Helper::getUsersRemoved(): Argument #1 ($currentObject) must be of type
Areanet\PIM\Entity\Base, Symfony\Component\HttpFoundation\JsonResponse given,
called in lib/contentfly/Classes/Api.php on line 488
```

`getSingle()` beantwortete „nicht gefunden" mit einer fertigen HTTP-Antwort — aus einer Klasse,
die kein Controller ist. Alle vier internen Aufrufer prüfen mit `if(!$object)`, und ein Objekt
ist wahr. Die Prüfung lief ins Leere, der Code danach arbeitete mit der Antwort weiter, als wäre
sie das Objekt. `/api/single` gab sie sogar aus: 200 und `data: {"headers": {}}`.

Behoben durch `return null`, plus die Entscheidung über den Statuscode dort, wo sie hingehört —
in `singleAction()`. Alle drei Endpunkte antworten jetzt mit 404.

### Der Nothelfer war selbst kaputt

Der TypeError erreichte die Fehlerkette gar nicht, sondern den globalen Handler. Und der starb:

```
GetResponseForExceptionEvent::__construct(): Argument #2 ($request) must be of type
Request, null given, called in lib/contentfly/bootstrap-web.php on line 55
```

Die Closure baute das Ereignis mit `$app['request']` — einem Pimple-Service, der `null` liefert,
wenn der Kernel den Request zu diesem Zeitpunkt schon abgeräumt hat. Symfony fing **diesen**
Fehler und zeigte seine „Whoops"-Seite: HTML, 500, ohne `message`, ohne `type`, ohne `status`.
Der ursprüngliche Fehler war verloren, genau in dem Moment, in dem man ihn braucht. Derselbe
Zugriff steht im `$app->error()`-Handler und stirbt am selben `null`.

Beides ist jetzt abgesichert: Request aus dem `request_stack`, notfalls aus den Globals; ohne
Request wird von JSON ausgegangen, weil dies eine API ist; und wenn niemand eine Antwort setzt,
eine eigene statt `null->sendHeaders()`.

### Zwei weitere Fehlerantworten, die der Task nennt

`GET /` beantwortete die Anwendung mit `302` nach `/` — endlos. Der Fehlerhandler leitete jede
Anfrage ohne JSON-Content-Type dorthin um; das stammt aus der Zeit, als unter `/` die
PIM-Oberfläche lag. Die ist mit Epic `012` entfallen, es gibt kein Zuhause mehr. Die Umleitung
ist gestrichen, der Debug-Zweig mit der Ausnahme im Klartext bleibt.

Und der Statuscode kommt jetzt aus `getStatusCode()`, **wenn** die Ausnahme keinen eigenen
trägt. Die Reihenfolge ist nicht beliebig: `SystemControllerProvider` wirft
`new AccessDeniedHttpException('Zugriff verweigert', null, 401)` — die 401 ist die Angabe des
Autors, `getStatusCode()` liefert dort die allgemeine 403 der Klasse. Ich hatte es zuerst
andersherum gebaut; **vier Charakterisierungstests haben es bemerkt.** `GET /` ergibt jetzt 405.

Dazu die Bereichsprüfung: Doctrine setzt in `getCode()` SQLSTATE-Werte wie `'42S02'`, und
`JsonResponse` weist alles zurück, was kein gültiger HTTP-Code ist — die Fehlerantwort wäre
dann selbst ein Fehler. Einen Aufruf, der das auslöst, habe ich in dieser Anwendung **nicht
herstellen können** (Doctrine-Ausnahmen werden vorher in eine `ContentflyException` mit Code 500
verpackt); die Prüfung steht trotzdem, weil sie in derselben Zeile mit der Vorrangregel
zusammenfällt, die belegt ist.

### `WEB_ROOT`

Kommt aus der Konfiguration, Vorgabe `/`. Gemessen, was vorher herauskam:

```
Location: /index.php/file/get/data/files/<id>/w.txt
```

Jetzt `/data/files/<id>/w.txt`. Damit ist die Auslieferung erstmals end-to-end prüfbar:
`testAuslieferungLiefertDenInhalt()` folgt der Umleitung und vergleicht den Inhalt byte-weise.
Das war der eigentliche Grund für diesen Punkt — ein Testnetz, das die Auslieferung nicht
abdeckt, lässt beim Kernel-Wechsel genau die Funktion ungeprüft, die jeder Client braucht.

### Was ich gebaut und wieder entfernt habe

Ein Listener auf `KernelEvents::EXCEPTION`, der einen `\Throwable` in eine `ErrorException`
umtauscht, weil Silex' `ExceptionListenerWrapper::shouldRun()` `\Exception` typisiert entgegen
nimmt. Die Gegenprobe — Listener herausgenommen, dieselbe Messung — ergab **dasselbe Ergebnis**:
Symfony 4.4 verpackt einen Nicht-`Exception` bereits in `GetResponseForExceptionEvent`. Der
Listener änderte nichts und ist wieder draussen.

Ebenso beim Statuscode: Ich hatte die Bereichsprüfung erst als eigene Closure gebaut, konnte
keinen Fall dafür herstellen und sie entfernt — und sie kam zurück, als die Vorrangregel für
`getStatusCode()` dieselbe Zeile brauchte.

### Nachweis, in beide Richtungen

| | |
|---|---|
| Volle Suite | `OK (244 tests, 608 assertions)`, 0 übersprungen |
| Vorher | 242 Tests; zwei neue in `FehlerantwortApiTest`, einer in `FileApiTest` |
| Gegenprobe | Alle vier neuen bzw. gedrehten Zusicherungen schlagen gegen den alten `bootstrap-web.php` fehl |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen |
| Postausgang | 0 Byte |

Acht Charakterisierungstests sind bewusst umgedreht: `/api/single` mit unbekannter Id
(200+Artefakt → 404), dasselbe nach dem Löschen, `/api/delete` und `/api/update` mit unbekannter
Id (500 → 404), zwei in `MultiupdateApiTest` (500 → 404), `GET /api/schema` ohne Token
(302 → 401) und `GET /system/do` (302 → 405). Vier Einträge in `breaking-changes.md`; Runbook,
`tests/README.md` und `prepare-test-environment.sh` nachgezogen.
