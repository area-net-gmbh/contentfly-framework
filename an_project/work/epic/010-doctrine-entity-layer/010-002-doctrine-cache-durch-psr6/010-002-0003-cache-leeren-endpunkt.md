---
id: 010-002-0003
title: Den Cache-Leeren-Endpunkt auf PSR-6 bringen
status: todo
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
- [ ] `flushSchemaCache()` benutzt `getQueryCache()`/`getMetadataCache()` und `clear()`.
- [ ] Ein nicht konfigurierter Cache führt nicht zu einem Fehler — die Methode prüft, statt sich auf die Bedingung an anderer Stelle zu verlassen.
- [ ] `SystemControllerApiTest` weist nach, dass die Caches tatsächlich geleert werden, nicht nur die Datei — sonst sichert der Test die halbe Methode zu.
- [ ] Die Antwort des Endpunkts bleibt unverändert.

## Verification
`./vendor/bin/phpunit --filter SystemControllerApiTest`, dann die volle Suite. Der neue
Nachweis: Cache füllen, Endpunkt rufen, Einträge zählen — vorher grösser null, danach null.
