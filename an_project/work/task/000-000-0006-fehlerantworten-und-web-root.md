---
id: 000-000-0006
title: Fehlerantworten und WEB_ROOT — 404 wird zu 500, Redirects sind umgebungsabhängig
status: todo
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
- [ ] Eine unbekannte Datei-ID beantwortet die API mit **404**, unabhängig von `APP_DEBUG`.
- [ ] Ein Zugriff ohne oder mit ungültigem Token beantwortet die API mit **401**.
- [ ] Der Debug-Exception-Handler übernimmt nicht mehr die Fälle, für die die Anwendung eine
      eigene Antwort vorsieht.
- [ ] Der Basispfad kommt aus der Konfiguration, nicht aus `PHP_SELF` — oder der Redirect wird
      absolut aufgebaut, sodass er unabhängig vom Webserver stimmt.
- [ ] Die Auslieferung ist unter dem Testserver end-to-end prüfbar; die entsprechenden
      Erwartungen in `tests/Integration/Api/FileApiTest.php` sind nachgezogen (heute halten sie
      301 und 500 fest).
- [ ] Beide Änderungen sind als Breaking Change für Epic `007` notiert — ein Client, der sich
      auf 500 bei unbekannter ID verlässt, sieht künftig 404.

## Verification
Gegen eine installierte Instanz: Eine unbekannte ID liefert 404. Ein Abruf einer vorhandenen
Datei liefert deren Inhalt — nicht nur einen Redirect, dem niemand folgen kann. Die Integrationssuite
läuft grün, nachdem die Erwartungen angepasst wurden.
