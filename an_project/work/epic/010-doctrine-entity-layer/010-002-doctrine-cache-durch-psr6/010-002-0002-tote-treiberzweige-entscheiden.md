---
id: 010-002-0002
title: Die nicht lauffähigen Treiberzweige entscheiden
status: review
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
- [x] Der `apc`-Zweig ist entfernt; ein konfiguriertes `apc` läuft nicht stillschweigend in die Vorgabe, sondern meldet sich.
- [x] Der `memcached`-Zweig benutzt `MemcachedAdapter` und prüft die Erweiterung, bevor er sie benutzt; die Meldung nennt Treiber und fehlende Erweiterung.
- [x] Beide Änderungen stehen in `an_project/docs/breaking-changes.md`, mit dem Grund und dem, was ein Projekt zu tun hat.
- [x] Dass der `apc`-Zweig nie laufen konnte, ist im Ergebnis belegt und nicht behauptet.

## Verification
`function_exists('apc_fetch')` und `class_exists('Memcached')` als Messung im Ergebnis
festhalten. Dazu ein Lauf mit `APP_CACHE_DRIVER=apc` und einer mit `=memcached`: Beide müssen
eine verständliche Meldung liefern statt eines Fatals.

## Ergebnis

**`apc` ist entfallen und wird ausdrücklich abgewiesen; `memcached` bleibt und hat zum ersten
Mal einen Server.**

### Dass `apc` nie laufen konnte, ist gemessen

| Probe | Ergebnis |
|---|---|
| `function_exists('apc_fetch')` | `false` |
| `function_exists('apcu_fetch')` | `false` (Erweiterung lokal nicht geladen — sie **existiert** aber für PHP 8) |
| `class_exists('Memcached')` | `false` |

Der Unterschied zwischen `apc` und `apcu` ist genau dieser: APCu gibt es für PHP 8, APC nicht.
`Doctrine\Common\Cache\ApcCache` ruft `apc_fetch()`, also war der Zweig auf jeder unterstützten
Version ein Fatal beim ersten Zugriff.

**Ein stiller Rückfall auf `filesystem` wäre bequemer und falsch gewesen.** Der Betreiber hätte
weiter geglaubt, sein Cache liege im geteilten Speicher. Der Zweig wirft jetzt mit einer
Meldung, die den Nachfolger nennt.

### Ein zweiter Befund: `memcached` hatte nie einen Server

Der alte Code baute `new Memcached()` und rief **kein** `addServer()`. Ein solcher Client hat
keinen Server und speichert nichts — der Zweig war also selbst dort wirkungslos, wo die
Erweiterung vorhanden war, und zwar lautlos. Das liess sich hier nicht ausführen (die
Erweiterung fehlt lokal), ist aber an der API eindeutig; als Beleg steht es so und nicht als
Messung.

Dazu teilte er sich **eine** Instanz für Abfrage- und Metadaten-Cache, während alle anderen
Zweige trennen. Beides ist behoben: `APP_CACHE_MEMCACHED_DSN` (Vorgabe
`memcached://localhost:11211`) und getrennte Namensräume.

### Was der Aufrufer sieht — ehrlich gemessen

| `APP_CACHE_DRIVER` | Antwort auf `/api/config` | Serverlog |
|---|---|---|
| `filesystem` | 200, normales JSON | — |
| `apcu` | **500, 0 Byte** | `RuntimeException: APP_CACHE_DRIVER = "apcu" verlangt die Erweiterung apcu; …` |
| `memcached` | **500, 0 Byte** | dieselbe Form |
| `apc` | **500, 0 Byte** | `… gibt es nicht mehr. … der Nachfolger heisst "apcu".` |

**Die Verbesserung liegt im Log, nicht in der Antwort.** Vorher starb der Aufruf an
`Call to undefined function apc_fetch()`, jetzt an einer Meldung, die die Einstellung und den
Weg nennt — der Rumpf ist in beiden Fällen leer.

Das ist keine Nachlässigkeit dieses Tasks, sondern eine Eigenschaft der Stelle: Die Ausnahme
fällt in `bootstrap.php` an, **bevor** der Kernel existiert; ein `kernel.exception`-Listener
kann nichts auffangen, was vor seiner Registrierung passiert. Der Befund ist allgemein — er
trifft jede Ausnahme aus dem Bootstrap — und deshalb als **`000-000-0024`** aufgeschrieben
statt hier miterledigt.

### Regel 3, zum dritten Mal in diesem Epic

Mit den letzten beiden Instanziierungen traf das PHPStan-Muster für `doctrine/cache` nichts mehr
und machte den Lauf rot. Gestrichen — obwohl das Paket erst mit `010-002-0004` geht, war das
Muster **hier** gegenstandslos geworden.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (267 tests, 640 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| Vier Treiber über HTTP durchgespielt | siehe Tabelle oben |
| `Doctrine\Common\Cache` im Bootstrap | keine Codestelle mehr, nur noch eine Erklärung |
