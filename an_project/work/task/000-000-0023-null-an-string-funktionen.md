---
id: 000-000-0023
title: null an strtolower, explode und method_exists
status: todo
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
- [ ] An allen drei Stellen ist geklärt und festgehalten, warum `null` ankommt.
- [ ] Behoben, ohne den `null`-Fall in einen leeren String zu verwandeln — es sei denn, das ist
      die begründet richtige Antwort.
- [ ] Der Testlauf auf `php:8.3-cli` protokolliert danach **nur noch** die Silex-Meldung.
- [ ] Die drei Zeilen sind aus `tools/ci/deprecations-ausnahmen.txt` gestrichen; das Gate meldet
      keine abgelaufene Ausnahme.
- [ ] Die Suite bleibt bei ihren bekannten Failures.

## Verification
Der vollständige Job in Docker auf `php:8.3-cli`, wie in `000-000-0021` und `006-005-0003`:

```
erwartet: 1 Paar aus Datei und Meldung (Silex), 1 davon ausgenommen, Gate gruen
```

Die Gegenprobe steckt im Gate selbst: Bleibt eine der drei Ausnahmen stehen, obwohl die Meldung
weg ist, wird der Lauf rot und nennt sie. Ein Vergessen fällt also auf, ohne dass jemand daran
denken muss.
