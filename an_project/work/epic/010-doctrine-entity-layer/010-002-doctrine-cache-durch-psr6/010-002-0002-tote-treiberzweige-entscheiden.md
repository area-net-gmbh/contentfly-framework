---
id: 010-002-0002
title: Die nicht lauffähigen Treiberzweige entscheiden
status: todo
depends_on: [010-002-0001]
---

# Die nicht lauffähigen Treiberzweige entscheiden

## Context
Zwei der vier Zweige können heute **nicht** laufen, und beide enden nicht mit einer Meldung,
sondern mit einem Fatal:

- **`apc`** ruft über `Doctrine\Common\Cache\ApcCache` die Funktion `apc_fetch()`. Die
  APC-Erweiterung gibt es für PHP 7 und 8 nicht mehr — nur noch APCu mit `apcu_fetch()`.
  Gemessen: `function_exists('apc_fetch')` ist `false`.
- **`memcached`** baut ein `new Memcached()`. Die Erweiterung fordert **kein** Manifest an
  (`composer.json` hat keine einzige `ext-*`-Angabe), und lokal fehlt sie.

**Entschieden am 2026-09-10:** `apc` fällt ersatzlos — ein Zweig, der auf keiner unterstützten
PHP-Version laufen kann, ist keine Konfigurationsmöglichkeit, sondern eine Falle. `memcached`
bleibt als `MemcachedAdapter`, aber mit einer **Prüfung auf die Erweiterung** statt eines
Fatals: Wer ihn konfiguriert und die Erweiterung nicht hat, soll das erfahren.

Verworfen wurde, `apc` stillschweigend auf APCu umzubiegen. Das erspart einem Bestandsprojekt
die Konfigurationsänderung, aber danach sagt die Konfiguration etwas anderes, als sie tut —
und genau solche stillen Umdeutungen sind in diesem Baum mehrfach teuer geworden.

## Acceptance criteria
- [ ] Der `apc`-Zweig ist entfernt; ein konfiguriertes `apc` läuft nicht stillschweigend in die Vorgabe, sondern meldet sich.
- [ ] Der `memcached`-Zweig benutzt `MemcachedAdapter` und prüft die Erweiterung, bevor er sie benutzt; die Meldung nennt Treiber und fehlende Erweiterung.
- [ ] Beide Änderungen stehen in `an_project/docs/breaking-changes.md`, mit dem Grund und dem, was ein Projekt zu tun hat.
- [ ] Dass der `apc`-Zweig nie laufen konnte, ist im Ergebnis belegt und nicht behauptet.

## Verification
`function_exists('apc_fetch')` und `class_exists('Memcached')` als Messung im Ergebnis
festhalten. Dazu ein Lauf mit `APP_CACHE_DRIVER=apc` und einer mit `=memcached`: Beide müssen
eine verständliche Meldung liefern statt eines Fatals.
