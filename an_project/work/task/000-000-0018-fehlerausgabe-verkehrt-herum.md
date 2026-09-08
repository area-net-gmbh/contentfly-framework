---
id: 000-000-0018
title: Die Fehlerausgabe ist verkehrt herum verdrahtet
status: todo
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
- [ ] Entschieden und begründet, ob das Framework die Produktionseinstellung erzwingt oder sie
      als Anforderung an das Deployment dokumentiert.
- [ ] Im Debug-Modus sind Deprecations sichtbar, nicht unterdrückt — oder es steht begründet
      dort, warum nicht.
- [ ] Geprüft, ob die Kopplung von Fehlerausgabe und Statuscode entkoppelt werden kann oder
      mit Epic `009` entfällt; das Ergebnis ist festgehalten.
- [ ] Die vier oben tabellierten Fälle liefern ihren gemeinten Statuscode **auch dann**, wenn
      `display_errors` eingeschaltet ist — oder es ist begründet, warum diese Zusicherung nicht
      gegeben wird.

## Verification
Der Nachweis ist der umgekehrte Weg von `008-005-0001`: Die Suite läuft gegen einen Testserver
mit **eingeschaltetem** `display_errors` und ist trotzdem grün. Heute scheitern dabei sechs
Tests; der Befund ist behoben, wenn keiner mehr scheitert.

Zusätzlich eine Umgebung ohne `php.ini` (etwa `php:8.3-cli` unverändert) prüfen: Eine Antwort
darf dort keine Dateipfade enthalten.
