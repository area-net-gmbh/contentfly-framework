---
id: 000-000-0018
title: Die Fehlerausgabe ist verkehrt herum verdrahtet
status: review
depends_on: [008-005-0001]
---

# Die Fehlerausgabe ist verkehrt herum verdrahtet

## Context
Beim Aufbau der CI-Pipeline (`008-005-0001`) war der erste Lauf rot: **sechs Tests, die lokal
grün sind**, meldeten `200` statt `405` oder `500`. Die Ursache ist keine Eigenheit der
Pipeline, sondern eine Eigenschaft der Anwendung — und sie betrifft jede Installation.

## Das Problem

### 1 — Die Produktionseinstellung wird nicht erzwungen
`lib/contentfly/bootstrap.php:47-51`:

```php
if(Adapter::getConfig()->APP_DEBUG){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL ^E_NOTICE^E_DEPRECATED);
}
```

Der Block hat **keinen `else`-Zweig.** Ist `APP_DEBUG` aus — also im Produktionsbetrieb —,
setzt das Framework weder `display_errors` noch `error_reporting`. Es gilt, was die `php.ini`
der Maschine sagt.

Das ist genau verkehrt herum: Die Einstellung wird dort gesetzt, wo sie unkritisch ist, und
dort weggelassen, wo sie zählt. Läuft die Anwendung in einer Umgebung ohne `php.ini` — das
offizielle `php:*`-Image lädt **keine**, und dieselbe Situation entsteht in vielen
Container-Deployments —, dann gilt der Compile-Default `display_errors=On`, und **eine
Produktionsinstanz liefert Deprecations, Warnings und Dateipfade an jeden Aufrufer aus.**

Zweiter Widerspruch derselben Zeilen: Im Debug-Modus werden Deprecations mit
`E_ALL ^E_NOTICE ^E_DEPRECATED` **unterdrückt** — also gerade dort, wo ein Entwickler sie
sehen will. Wer sie sehen soll, sieht sie nicht; wer sie nicht sehen soll, bekommt sie.

### 2 — Die Folge: falsche Statuscodes
Das ist der Teil, der die Tests rot machte, und er wiegt schwerer als die Ausgabe selbst.

PHP schreibt eine Deprecation **direkt in den Antwortstrom**. Passiert das, bevor Silex den
Statuscode setzt, sind die Header schon unterwegs — und die Antwort trägt `200`, obwohl die
Anwendung `405` oder `500` meint. Beobachtet an:

| Fall | erwartet | geliefert |
|---|---|---|
| `GET /system/do` (Route ist POST-only) | 405 | **200** |
| `GET /api/mail` (Route ist POST-only) | 405 | **200** |
| `POST /system/do` ohne `method` | 500 | **200** |
| Schreibversuch ohne Berechtigung (drei Fälle) | 500 | **200** |

Ein Client kann in dieser Lage nicht unterscheiden, ob eine Anfrage gelungen ist. Ein
Sync-Client, der auf den Statuscode hört, hält eine abgewiesene Schreiboperation für
erfolgreich.

**Der Zusammenhang ist zufällig, nicht systematisch:** Es hängt daran, ob auf dem Weg zur
Antwort zufällig eine Deprecation feuert. Dieselbe Route kann sich unter zwei PHP-Versionen
verschieden verhalten — genau das war der Unterschied zwischen lokal (PHP 8.3.26, grün) und
Container (PHP 8.3.33, rot).

## Umfang

Zu entscheiden und zu begründen:

- **Die Produktionseinstellung erzwingen.** Ein `else`-Zweig, der `display_errors` ausschaltet
  und `log_errors` einschaltet, wäre die naheliegende Antwort. Zu klären ist, ob das Framework
  das überhaupt vorschreiben soll oder ob es Sache des Deployments ist — dann gehört es
  wenigstens dokumentiert und in `an_project/docs/deployment.md` als Anforderung an die
  Zielumgebung festgehalten.
- **Deprecations im Debug-Modus sichtbar machen.** `E_ALL ^E_NOTICE ^E_DEPRECATED` widerspricht
  der Vorgabe aus `an_project/docs/tech-stack.md`, deprecation-frei zu bauen. Wer nach
  Deprecations sucht, findet sie ausgerechnet im Debug-Modus nicht.
- **Die Ausgabe vom Statuscode entkoppeln.** Auch mit `display_errors=Off` bleibt die
  Kopplung bestehen — sie fällt nur nicht mehr auf. Ob ein Output-Buffer im Front-Controller
  das sauber löst, oder ob das mit dem Kernel-Tausch (Epic `009`) ohnehin verschwindet, ist zu
  prüfen. Symfony setzt hier eigene Fehler-Handler ein.

## Abgrenzung
Die Frage, ob Fehlerantworten überhaupt das richtige Format und den richtigen Statuscode
haben (`500` statt `401`/`403`/`400`), ist `000-000-0006`. Hier geht es allein darum, dass
ein **korrekt gemeinter** Statuscode durch eine Nebenwirkung der Fehlerausgabe verlorengeht.

Die Pipeline aus `008-005-0001` umgeht das Problem bereits mit `display_errors=Off` — das ist
die richtige Einstellung, aber sie behebt die Ursache nicht, sie verdeckt sie nur an einer
Stelle.

## Acceptance criteria
- [x] Entschieden und begründet, ob das Framework die Produktionseinstellung erzwingt oder sie
      als Anforderung an das Deployment dokumentiert.
- [x] Im Debug-Modus sind Deprecations sichtbar, nicht unterdrückt — oder es steht begründet
      dort, warum nicht.
- [x] Geprüft, ob die Kopplung von Fehlerausgabe und Statuscode entkoppelt werden kann oder
      mit Epic `009` entfällt; das Ergebnis ist festgehalten.
- [x] Die vier oben tabellierten Fälle liefern ihren gemeinten Statuscode **auch dann**, wenn
      `display_errors` eingeschaltet ist — oder es ist begründet, warum diese Zusicherung nicht
      gegeben wird.

## Verification
Der Nachweis ist der umgekehrte Weg von `008-005-0001`: Die Suite läuft gegen einen Testserver
mit **eingeschaltetem** `display_errors` und ist trotzdem grün. Heute scheitern dabei sechs
Tests; der Befund ist behoben, wenn keiner mehr scheitert.

Zusätzlich eine Umgebung ohne `php.ini` (etwa `php:8.3-cli` unverändert) prüfen: Eine Antwort
darf dort keine Dateipfade enthalten.

## Ergebnis
**Das Framework erzwingt die Produktionseinstellung.** Der Block hat jetzt einen `else`-Zweig,
und der Nachweis ist der umgekehrte Weg von `008-005-0001`: Die Suite läuft gegen einen
Testserver mit **eingeschaltetem** `display_errors` und ist grün.

```
php -d display_errors=On -S 127.0.0.1:8172 tests/router.php
→ OK (237 tests, 584 assertions)
```

Vorher scheiterten unter dieser Bedingung sechs Tests.

### Warum erzwingen und nicht dokumentieren
Der Task liess beides zu. Erzwingen, **weil der Schaden eintritt, wenn nichts konfiguriert
ist**: Das offizielle `php:*`-Image lädt keine `php.ini`, und dann gilt der Compile-Default
`display_errors=On`. Der unsichere Zustand ist damit der Standardzustand — eine Anforderung an
die Zielumgebung wäre nur so gut wie die Umgebung, die sie liest.

Nachgemessen genau in diesem Fall:

```
php.ini geladen       : KEINE
display_errors vorher : '1'      ← Compile-Default
APP_DEBUG             : false
display_errors nachher: '0'      ← das Framework
log_errors     nachher: '1'
```

Beide Zweige einzeln belegt, jeweils gegen die entgegengesetzte Startbedingung:

| Zweig | gestartet mit | danach |
|---|---|---|
| `APP_DEBUG=0` | `display_errors=On` | **`0`**, `log_errors` **`1`** |
| `APP_DEBUG=1` | `display_errors=Off` | **`1`** |

`log_errors` bleibt an: Was nicht ausgeliefert wird, soll auffindbar sein — und es ist die
Quelle, aus der das „0 Deprecations"-Gate aus `006-005` liest.

### Deprecations sind im Debug-Modus jetzt sichtbar
Aus `E_ALL ^E_NOTICE ^E_DEPRECATED` wird `E_ALL`, in **beiden** Zweigen. Der alte Ausdruck
unterdrückte Deprecations ausgerechnet dort, wo ein Entwickler sie sehen will — und
widersprach damit der Vorgabe aus `tech-stack.md`, deprecation-frei zu bauen. Wer sie sehen
sollte, sah sie nicht; wer sie nicht sehen sollte, bekam sie.

### Die Kopplung: geprüft, bewusst nicht hier gelöst
Der Task verlangte eine Prüfung, kein Ergebnis. Sie fällt so aus:

**Ein `ob_start()` im Bootstrap würde es lösen** — nichts ginge hinaus, bevor die Antwort
fertig ist, die Header blieben änderbar. Und es ist **bewusst nicht gesetzt**: Die
Dateiauslieferung antwortet mit einer `StreamedResponse`
(`FileController::getAction()`, Zeile 293). Ein Ausgabepuffer zöge jede ausgelieferte Datei
durch den Speicher — ein Fehler, der teurer wäre als der behobene.

**Im Produktionsbetrieb kann die Kopplung nicht mehr greifen**, weil nichts mehr in den Strom
geschrieben wird. Im Debug-Modus bleibt sie möglich, dort ist eine ausgegebene Deprecation
aber sichtbar und nicht stumm.

**Die strukturelle Lösung kommt mit Epic `009`:** Symfonys Fehlerbehandlung wandelt Fehler in
Ausnahmen um, statt sie auszugeben. Damit verschwindet die Ursache, nicht nur ihre Wirkung.
Der Vermerk steht im Kommentar über dem Block, damit niemand `ob_start()` nachträgt, ohne die
`StreamedResponse` zu kennen.

### Die vier Fälle aus der Tabelle
Sie liefern ihren gemeinten Statuscode auch bei eingeschaltetem `display_errors` — **weil das
Framework die Einstellung überschreibt**. Zwei der vier sind inzwischen gegenstandslos:
`GET /api/mail` gibt es seit `000-000-0016` nicht mehr, und `POST /system/do` ohne `method`
läuft seit `000-000-0023` ohne Deprecation. Die übrigen deckt die grüne Suite ab.

Das ist die ehrliche Fassung der Zusicherung: Nicht die Kopplung ist behoben, sondern ihre
Voraussetzung ist beseitigt.

### Verification
| Prüfung | Ergebnis |
|---|---|
| Suite gegen Server mit `display_errors=On` | **OK (237 tests, 584 assertions)** |
| Suite wie in der Pipeline (`display_errors=Off`) | **OK (237 tests, 584 assertions)** |
| Laufzeit, `APP_DEBUG=0`, gestartet mit `display_errors=On` | `0` / `log_errors=1` |
| Laufzeit, `APP_DEBUG=1`, gestartet mit `display_errors=Off` | `1` |
| `php:8.3-cli` ohne `php.ini` | Default `1` → Framework setzt `0` |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen |

**Ein Fehlgriff beim Messen:** Mein erster Probelauf meldete `APP_DEBUG: true` und
`display_errors` unverändert `1` — ich hatte die Umgebungsvariablen nicht gesetzt, und
`custom/config.php` schaltet ohne `APP_ENV` auf `dev`. Der Probelauf hatte also den
Debug-Zweig gemessen und nicht den Produktionszweig. Mit gesetzten Variablen wiederholt.
