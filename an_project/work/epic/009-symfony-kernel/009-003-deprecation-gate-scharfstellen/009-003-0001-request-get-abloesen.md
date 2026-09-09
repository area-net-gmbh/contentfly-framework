---
id: 009-003-0001
title: Request::get() ablösen — 74 Stellen
status: done
depends_on: []
---

# Request::get() ablösen — 74 Stellen

## Context
Der mit Abstand grösste Posten des Gates. Symfony 7.4 hat `Request::get()` als deprecated
markiert:

> since Symfony 7.4, use properties `->attributes`, `query` or `request` directly instead

PHPStan meldet **74 Fundstellen**, die meisten im `ApiController`. Das ist keine Formalie: In
Symfony 8 fällt die Methode weg, und die Zusicherung aus `tech-stack.md` — der spätere Sprung
auf Symfony 8.4 LTS soll ein reiner Constraint-Bump bleiben — hängt genau daran.

**`get()` ist nicht durch eine einzige Quelle zu ersetzen.** Die Methode sucht der Reihe nach in
`attributes`, `query` und `request`, und welche davon zählt, ist je Aufrufstelle verschieden:

- Die Nutzlast der API kommt aus `$request->request` — `BaseControllerProvider` dekodiert den
  JSON-Rumpf und legt ihn dort ab.
- Die Pfadplatzhalter (`/file/get/{id}/{size}`) stehen in `$request->attributes`.
- `$request->query` spielt für diese API kaum eine Rolle, ist aber zu prüfen statt anzunehmen.

Eine mechanische Ersetzung durch `->request->get()` wäre deshalb falsch. Jede Stelle braucht die
Frage: Woher kommt dieser Wert?

## Acceptance criteria
- [x] Keine Fundstelle von `Request::get()` mehr im eigenen Baum; PHPStan meldet dazu nichts.
- [x] Je Aufrufstelle ist die **richtige** Quelle gewählt, nicht die bequemste. Wo eine Stelle
      aus mehr als einer Quelle bedient werden kann, steht die Entscheidung als Kommentar dabei.
- [x] Die Suite aus Epic `008` bleibt grün, ohne inhaltliche Änderung an einer Zusicherung.
      Sie deckt beide Quellen ab: Nutzlast über `postJson()`, Pfadplatzhalter über `FileApiTest`.
- [x] Wo eine Stelle heute stillschweigend aus zwei Quellen liest und das ein Verhalten ist, auf
      das sich jemand verlassen könnte, ist es benannt — nicht wegvereinfacht.

## Verification
`./vendor/bin/phpstan analyse --memory-limit=1G` meldet keine `Request::get()`-Deprecation mehr.
Volle Suite grün. Dazu ein Aufruf mit Pfadplatzhaltern (`/file/get/{id}/s-{size}/{alias}`) und
einer mit JSON-Rumpf.

## Ergebnis

**74 Fundstellen abgelöst, PHPStan von 115 auf 41 Meldungen.** Die Suite bleibt grün:
`OK (266 tests, 640 assertions)`.

### Die Quellen, je Stelle bestimmt

| Quelle | Stellen | Was dort liegt |
|---|---|---|
| `$request->request` | 69 | die Nutzlast — `BaseControllerProvider` dekodiert den JSON-Rumpf und legt ihn dort ab |
| `$request->attributes` | 4 | `_controller`, das der Router setzt |
| `query` **und** `request` | 1 | der `_token`-Rückfall |

**Die Pfadplatzhalter kamen gar nicht vor.** `FileController::getAction()` nimmt `id`, `alias`,
`size` und `variant` als Action-Argumente entgegen, nicht über den Request. Und die drei
GET-Routen — `configAction`, `schemaAction`, `logoutAction` — nehmen überhaupt keinen Request
entgegen, lesen also auch keinen Query-String. Damit blieb `query` als Quelle nur an einer
einzigen Stelle übrig.

### Die eine mehrdeutige Stelle

`checkToken()` las den Token unter anderem als `_token`-Parameter. Zwei Quellen sind dafür
plausibel: der Query-String (ein Link, den jemand anklickt) und der Rumpf (ein POST). Beide
bleiben, in derselben Reihenfolge wie vorher, mit der Begründung an der Stelle: **Das ist der
Anmeldeweg.** Eine Quelle wegzulassen hiesse, eine Aufrufform stillschweigend abzuschalten und
es erst zu merken, wenn ein Bestandsprojekt sich nicht mehr anmelden kann. Die Attribute kommen
nicht in Frage — keine Route definiert einen Platzhalter `_token`.

### Der Befund, der den Weg geändert hat

Der erste Durchgang ersetzte `$request->get(…)` durch `$request->request->get(…)`. Die Suite
ging auf **59 Failures und 6 Errors**, und die Antwort sagte, warum:

```
Input value "data" contains a non-scalar value.
BadRequestHttpException
```

**`InputBag::get()` erlaubt seit Symfony 6 nur skalare Werte.** `Request::get()` hatte diese
Beschränkung nicht. Betroffen ist genau das, was diese API ausmacht: `data`, `where`,
`properties`, `objects`, `order` sind Objekte oder Listen. Und `InputBag::all($schluessel)` ist
kein Ausweg — es wirft umgekehrt bei einem skalaren Wert, und `lastModified` ist mal eine
Zeichenkette, mal eine Liste je Entity.

Gelesen wird deshalb der Beutel selbst: `($request->request->all()['schluessel'] ?? $vorgabe)`.
Das ist genau, was `Request::get()` für diesen Beutel tat, ohne die Typbeschränkung — und die
Quelle steht an jeder Stelle im Klartext, statt in einer Methode zu verschwinden, die drei
Beutel nacheinander durchsucht.

Ersetzt wurde mit einem Klammerzähler statt mit einem regulären Ausdruck: Zwölf Aufrufe tragen
einen Vorgabewert, und einer davon ist
`Config\Adapter::getConfig()->FRONTEND_ITEMS_PER_PAGE` — ein Muster hätte dort in der Mitte
geschnitten.

### Ein Test war betroffen, und zwar zu Recht

`testDasNotschlossKenntNurNochUpdateDatabase()` liest den **Quelltext** des
`SystemControllerProvider` und prüft, dass die Bedingung des Notschlosses dort steht. Die
Bedingung ist dieselbe geblieben, ihre Schreibweise nicht. Die Zusicherung ist auf die neue
Schreibweise gezogen, mit dem Grund daneben — sie prüft weiterhin, dass `updateDatabase` die
einzige Ausnahme ist.

### Nachweis

| | |
|---|---|
| PHPStan | 115 → **41** Meldungen; keine `Request::get()`-Deprecation mehr |
| Volle Suite | `OK (266 tests, 640 assertions)`, 0 übersprungen |
| Nutzlast über JSON | `POST /api/insert` mit verschachteltem `data` — 200 |
| Pfadplatzhalter | `GET /file/get/{id}` liefert die Datei byte-gleich |
