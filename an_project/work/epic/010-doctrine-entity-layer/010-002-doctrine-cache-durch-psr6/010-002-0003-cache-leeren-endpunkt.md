---
id: 010-002-0003
title: Den Cache-Leeren-Endpunkt auf PSR-6 bringen
status: done
depends_on: [010-002-0001]
---

# Den Cache-Leeren-Endpunkt auf PSR-6 bringen

## Context
`SystemController::flushSchemaCache()` ruft `getQueryCacheImpl()->deleteAll()` und
`getMetadataCacheImpl()->deleteAll()`. Das ist `doctrine/cache`-API; PSR-6 heisst dort
`clear()`, und die Getter heissen `getQueryCache()`/`getMetadataCache()`.

**Der Task steht getrennt, weil hier ein API-Verhalten hängt** und nicht bloss Konfiguration:
`POST /system/do` mit `method=flushSchemaCache` ist ein Endpunkt, den ein Projekt aufruft.
`SystemControllerApiTest` deckt ihn ab — allerdings nur die Datei `data/cache/schema.cache`,
nicht die beiden Caches.

**Eine Falle steckt schon im Ist-Stand:** Die Getter können `null` liefern, wenn kein Cache
konfiguriert ist — und genau das ist der Fall, sobald `APP_DEBUG` an ist oder die Konsole
läuft, denn der ganze Cache-Block im Bootstrap steht hinter dieser Bedingung. Der Aufruf ist
zwar seinerseits von `!APP_DEBUG` geschützt, aber die zwei Bedingungen stehen an verschiedenen
Stellen und decken sich nur zufällig.

## Acceptance criteria
- [x] `flushSchemaCache()` benutzt `getQueryCache()`/`getMetadataCache()` und `clear()`.
- [x] Ein nicht konfigurierter Cache führt nicht zu einem Fehler — die Methode prüft, statt sich auf die Bedingung an anderer Stelle zu verlassen.
- [x] `SystemControllerApiTest` weist nach, dass die Caches tatsächlich geleert werden, nicht nur die Datei — sonst sichert der Test die halbe Methode zu.
- [x] Die Antwort des Endpunkts bleibt unverändert.

## Verification
`./vendor/bin/phpunit --filter SystemControllerApiTest`, dann die volle Suite. Der neue
Nachweis: Cache füllen, Endpunkt rufen, Einträge zählen — vorher grösser null, danach null.

## Ergebnis

**`flushSchemaCache()` benutzt PSR-6, und der Test sichert jetzt zu, was die Methode tut.**
Die Suite wächst von 267 auf **268**.

### Die Prüfung auf `null` war nötig, nicht vorsorglich

Vorher stand dort `if(!Adapter::getConfig()->APP_DEBUG)`. Der Cache wird im Bootstrap aber
unter **zwei** Bedingungen eingerichtet: `!APP_DEBUG` **und** `!APPCMS_CONSOLE`. An die zweite
war nicht gedacht. Dass es gutging, lag daran, dass diese Methode über HTTP gerufen wird und
die Konstante dort nie gesetzt ist — zwei Bedingungen an verschiedenen Stellen, die sich
zufällig decken. Die Methode fragt jetzt selbst, statt sich auf eine Verabredung auf Distanz zu
verlassen.

### Der Test sicherte bisher die halbe Methode zu

Die beiden vorhandenen Tests prüfen die Meldung und die Datei `data/cache/schema.cache`. Die
**Doctrine-Caches**, um die es der Methode eigentlich geht, prüfte keiner. Der Umbau von
`deleteAll()` auf `clear()` wäre daran nicht aufgefallen — und ein Aufruf, der gar nichts mehr
leert, ebensowenig.

### Eine Zusicherung, die ich erst falsch geschnitten hatte

Der erste Entwurf verlangte, das Verzeichnis sei danach **leer**. Der Test wurde rot: 7 Dateien.
Gemessen, ob es dieselben sind — sie sind es **nicht**. Der Flush löscht alles, und der Request
läuft danach weiter und stellt erneut Abfragen, die neu im Cache landen.

Direkt über HTTP gegengeprüft, ohne Testrahmen: drei Dateien vorher, **null** unmittelbar nach
dem Aufruf.

Der Test verlangt jetzt, dass **keine Datei von vorher überlebt**. Das ist die Eigenschaft, die
der Endpunkt zusagt; ein leeres Verzeichnis wäre ihm zugeschrieben, ohne dass er es verspricht.

### Was `010-002-0004` damit los ist

`getQueryCacheImpl()` wird nicht mehr gerufen. Die Brücke
`Doctrine\Common\Cache\Psr6\DoctrineProvider`, die ORM 2.20 dafür baut, liegt in
`doctrine/cache` — mit diesem Task ist die letzte Abhängigkeit davon gefallen.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `SystemControllerApiTest` | `OK (28 tests)` |
| Neuer Test allein | `OK (1 test, 2 assertions)` |
| Volle Suite | `OK (268 tests, 642 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Flush über HTTP, ohne Testrahmen | 3 Dateien vorher, 0 danach |
| Antwort des Endpunkts | unverändert `Schema-Cache wurde geleert!`, HTTP 200 |
