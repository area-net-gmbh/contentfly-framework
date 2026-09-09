---
id: 000-000-0023
title: null an strtolower, explode und method_exists
status: review
depends_on: [006-005-0003]
---

# null an strtolower, explode und method_exists

## Context
Gefunden mit `006-005-0003` beim Scharfschalten des Deprecation-Gates. Der Testserver
protokolliert auf **PHP 8.3** 148 Zeilen; als Paare aus Datei und Meldung sind es vier, und
drei davon liegen im eigenen Code:

| Datei | Meldung | Zeilen im Log |
|---|---|---|
| `lib/contentfly/Classes/Controller/Provider/BaseControllerProvider.php` | `strtolower(): Passing null to parameter #1 ($string)` | 5 |
| `lib/contentfly/Entity/Base.php` | `explode(): Passing null to parameter #2 ($string)` | 3 |
| `lib/contentfly/Controller/SystemController.php` | `method_exists(): Passing null to parameter #2 ($method)` | 1 |

Die vierte stammt aus Silex und fällt mit Epic `009`.

**Das ist kein Stilproblem.** Seit PHP 8.1 ist es deprecated, `null` an einen `string`-Parameter
einer internen Funktion zu übergeben; in einer künftigen Fassung wird daraus ein `TypeError`.
Der Code läuft heute, weil PHP `null` noch stillschweigend zu `""` macht — und genau darauf
verlässt sich die Logik an diesen Stellen, ohne es zu sagen.

Bis dahin sind die drei Stellen in `tools/ci/deprecations-ausnahmen.txt` eingetragen, mit
Verweis auf dieses Ticket. **Das Gate wird grün, sobald sie behoben sind** — und meldet die
Ausnahmen dann als abgelaufen, sie sind also mit zu streichen.

## Umfang

### Nicht einfach casten
`(string)$wert` würde die Meldung zum Verschwinden bringen und die Frage verstecken. An jeder
der drei Stellen ist zuerst zu klären, **warum** dort `null` ankommt:

- Ist `null` ein gültiger Zustand? Dann gehört er behandelt — ein früher `return`, ein
  Standardwert, eine Prüfung. Nicht ein Cast, der ihn in einen leeren String verwandelt und so
  tut, als sei nichts gewesen.
- Ist `null` ein Fehler? Dann ist die Deprecation der Bote, nicht die Nachricht, und die
  eigentliche Ursache liegt weiter oben.

`Entity/Base.php` ist dabei die heikelste der drei: Die Klasse ist Basis aller Entities, und was
dort passiert, passiert überall.

### Was der Nachweis leisten muss
Die Suite deckt die Stellen ab — sie sind ja im Testlauf aufgetreten. Ein Test, der den
`null`-Fall festhält, ist trotzdem sinnvoll: Sonst belegt nur die Abwesenheit einer
Log-Zeile, dass sich etwas geändert hat, und die verschwindet auch beim Cast.

## Abgrenzung
Nicht die Silex-Meldung — Fremdpaket, fällt mit Epic `009`.

Nicht die implizit nullable Parameter aus `000-000-0022`. Die sehen ähnlich aus, sind aber eine
andere Ursache (Signatur statt Aufruf) und treten erst auf PHP 8.4 auf.

Keine Änderung am Gate selbst.

## Acceptance criteria
- [x] An allen drei Stellen ist geklärt und festgehalten, warum `null` ankommt.
- [x] Behoben, ohne den `null`-Fall in einen leeren String zu verwandeln — es sei denn, das ist
      die begründet richtige Antwort.
- [x] Der Testlauf auf `php:8.3-cli` protokolliert danach **nur noch** die Silex-Meldung.
- [x] Die drei Zeilen sind aus `tools/ci/deprecations-ausnahmen.txt` gestrichen; das Gate meldet
      keine abgelaufene Ausnahme.
- [x] Die Suite bleibt bei ihren bekannten Failures.

## Verification
Der vollständige Job in Docker auf `php:8.3-cli`, wie in `000-000-0021` und `006-005-0003`:

```
erwartet: 1 Paar aus Datei und Meldung (Silex), 1 davon ausgenommen, Gate gruen
```

Die Gegenprobe steckt im Gate selbst: Bleibt eine der drei Ausnahmen stehen, obwohl die Meldung
weg ist, wird der Lauf rot und nennt sie. Ein Vergessen fällt also auf, ohne dass jemand daran
denken muss.

## Ergebnis
**Von vier Stellen auf eine.** Das Deprecation-Gate meldet auf PHP 8.3 nur noch die Meldung aus
Silex — und es hat die Streichung der drei Ausnahmen selbst eingefordert, statt dass jemand
daran denken musste.

| | vorher | nachher |
|---|---|---|
| protokollierte Zeilen | 148 | **110** |
| Paare aus Datei und Meldung | 4 | **1** |
| Einträge in der Ausnahmeliste | 4 | **1** |

### Warum `null` ankommt — an jeder der drei Stellen einzeln
Der Task verlangte, das zu klären statt zu casten. Es sind drei verschiedene Antworten:

**`BaseControllerProvider` (2 Aufrufe, before- und after-Hook).** `_controller` fehlt bei
internen Anfragen ganz — dann hat der Hook nichts zu verteilen. Der Code wusste das bereits: Er
prüfte direkt danach `if (empty($controllerAction)) return;`. Nur lag die Prüfung **hinter**
dem `strtolower()`. Sie steht jetzt davor, als `is_string()`-Prüfung auf den Rohwert; die
`empty()`-Prüfung bleibt zusätzlich stehen, damit ein `_controller` von `"0"` sich weiter
verhält wie bisher.

**`Entity/Base.php` (`hasUserId`, `hasGroupId`).** `users` und `groups` sind nullable Spalten:
kein Eintrag heisst keine Treffer. Auffällig ist, dass die **danebenliegenden** Methoden
`getUsers()` und `getGroups()` den Fall längst mit `if($this->users)` abfangen — nur die
`has…Id()`-Paare nicht. Es war kein unbekannter Zustand, nur ein vergessener.

**`SystemController::doAction()`.** `method` fehlt, wenn der Aufrufer sie nicht mitschickt.
Der Fall endet in derselben Ausnahme wie eine unbekannte Methode — er tat es vorher auch schon,
nur mit einer Deprecation auf dem Weg dorthin.

### Kein Cast, nirgends
An keiner Stelle steht ein `(string)`. Der Task hatte das ausdrücklich untersagt, und die
Begründung hat sich bestätigt: Alle drei Fälle waren **gültige Zustände**, die der Code an
anderer Stelle bereits kannte. Ein Cast hätte die Meldung beseitigt und den leeren String als
Datenwert erfunden.

Das Verhalten ist unverändert. `explode(',', null)` ergab `array("")`, jetzt `array()` — für
`in_array($id, $ids)` in beiden Fällen `false`. Die Suite belegt es.

### Das Gate hat sich selbst bewährt
Nach dem Beheben wurde der Lauf **rot**, nicht grün:

```
✗ 3 Ausnahme(n) greifen nicht mehr:
    lib/contentfly/Classes/Controller/Provider/BaseControllerProvider.php | strtolower(): Passing null
    lib/contentfly/Entity/Base.php | explode(): Passing null
    lib/contentfly/Controller/SystemController.php | method_exists(): Passing null
```

Genau das war die Absicht von `006-005-0002` und `-0003`: Eine Ausnahme läuft **in der Sache**
ab. Derselbe Lauf, der sie überflüssig macht, fordert ihre Streichung ein — niemand muss daran
denken.

Die Liste steht damit auf **einer** Ursache: einem Fremdpaket, das mit Epic `009` fällt. Wächst
sie wieder, ist das eine Aussage und keine Gewohnheit. Der Vermerk dazu steht in der Datei.

### Verification
| Prüfung | Ergebnis |
|---|---|
| volle Suite mit `CI=true` | **OK (249 tests, 605 assertions)**, 0 übersprungen |
| Deprecation-Gate | grün, **1 Paar, 1 ausgenommen** |
| Postausgang der Versandfalle | 0 Byte |

`SystemControllerApiTest::testEineFehlendeMethodeEndetEbenfallsInEinemFehler()` bleibt
unverändert grün: Er sichert Status 500 zu, und den gibt es weiterhin — jetzt ohne die
Deprecation, die sein Kommentar noch als „ab PHP 9 ein TypeError" ankündigte.
