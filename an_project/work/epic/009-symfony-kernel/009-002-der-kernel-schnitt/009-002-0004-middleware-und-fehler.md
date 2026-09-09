---
id: 009-002-0004
title: Middleware und Fehlerbehandlung als Listener
status: review
depends_on: [009-002-0002]
---

# Middleware und Fehlerbehandlung als Listener

## Context
`$app->before()`, `$app->after()` und `$app->error()` sind Silex-Aufrufe. Darunter liegen
Listener auf `kernel.request`, `kernel.response` und `kernel.exception` — der neue Kernel
bekommt sie direkt, die drei Methoden bleiben als Fassade erhalten, weil `custom/app.php` sie
benutzt.

**Die Reihenfolge ist Sicherheitslogik, kein Detail.** `an_project/docs/technical.md` sagt es
ausdrücklich: Silex nimmt bei `before()`/`after()` ein Prioritätsargument, und wo es gesetzt
ist, war es Absicht. Die effektive Reihenfolge ist **nachzuweisen, nicht aus der
Registrierungsreihenfolge zu raten**.

**Die Fehlerantwort ist frisch repariert und darf sich nicht verschieben.** `000-000-0006` hat
dort vier Dinge in Ordnung gebracht, die alle erhalten bleiben müssen:

- Der Statuscode kommt aus `getCode()`, mit `getStatusCode()` als Rückfall, und nur wenn er ein
  gültiger HTTP-Code sein kann.
- Der Rumpf trägt `message`, `type` und `status` — auch im Zweig für gewöhnliche Ausnahmen, wo
  der Schlüssel früher fehlte.
- Es gibt **keine** Umleitung auf `/` mehr.
- `FileNotFoundException` ergibt 404.

Dazu der Nothelfer aus `bootstrap-web.php`: die Closure, die einspringt, wenn die Anwendung gar
nicht mehr erreicht wird. Sie starb an einem `null`-Request, bis `000-000-0006` sie abgesichert
hat. Ob es sie unter Symfony überhaupt noch braucht, ist zu **messen** — Symfonys eigener
ExceptionListener nimmt `\Throwable` entgegen, was der Grund für ihre Existenz war.

## Acceptance criteria
- [x] `before()`, `after()` und `error()` bleiben als Aufrufe erhalten, samt Prioritätsargument;
      `custom/app.php` läuft unverändert.
- [x] Ein Test belegt die **effektive** Ausführungsreihenfolge mehrerer Hooks mit Prioritäten —
      nicht die Registrierungsreihenfolge.
- [x] Ein before-Hook, der eine Response zurückgibt, bricht die Verarbeitung ab. Das ist der
      dokumentierte Weg, einen Request zu blockieren (`custom/app.php`).
- [x] Die vier Punkte aus `000-000-0006` gelten unverändert; `FehlerantwortApiTest` bleibt grün.
- [x] Die `pim.controller.before.*`-Ereignisse werden weiter in derselben Staffelung verteilt —
      drei Ebenen, vom spezifischsten zum allgemeinsten.
- [x] CORS-Header und die Security-Header stehen auf jeder Antwort wie bisher.
- [x] Über den Nothelfer ist entschieden: entweder gemessen überflüssig und entfernt, oder
      behalten mit Begründung.

## Verification
`FehlerantwortApiTest`, `AuthApiTest`, `RouteSecurityApiTest` und `LogSideEffectApiTest` grün —
der letzte prüft die Hook-Staffelung. Dazu ein Aufruf mit einem absichtlich ausgelösten
PHP-Fehler: Er muss als JSON der Anwendung ankommen, nicht als HTML.

## Ergebnis

### Der wichtigste Befund: Symfonys Kernel fängt in der Vorgabe **keine** `\Error`

`HttpKernel::__construct()` hat einen fünften Parameter `bool $handleAllThrowables = false`.
Mit der Vorgabe fällt ein `TypeError` durch den ganzen Kernel hindurch, und der Aufrufer
bekommt eine **leere 500** — gemessen: Rumpf 0 Byte.

**Das ist exakt der Befund aus `000-000-0006`**, nur mit Symfonys Kernel statt mit Silex'
`ExceptionListenerWrapper`. Dieselbe Ursache, dieselbe Wirkung, anderes Vorzeichen: Damals war
es ein typisiertes `\Exception` in fremdem Code, hier ein Vorgabewert. Der Kernel wird deshalb
mit `handleAllThrowables: true` gebaut, und `FehlerantwortApiTest` — der Test, der aus
`000-000-0006` stammt — prüft beide Hälften: JSON statt HTML, und der Rumpf trägt `message`,
`type` und `status`.

Hätte ich diesen Test nicht schon gehabt, wäre der Fehler durchgerutscht: Die Antwort ist ein
500 mit leerem Rumpf, und ein Test, der nur den Statuscode prüft, wäre grün geblieben.

### Was sonst zu tun war

**Der Fehlerhandler nimmt jetzt `\Throwable`.** Silex reichte eine `\Exception` — es hatte
einen Nicht-Exception vorher selbst verpackt. Symfonys `ExceptionEvent` liefert den Throwable,
wie er geworfen wurde; bliebe die Angabe auf `Exception`, würde ein TypeError den Handler mit
einem TypeError erschlagen.

**`Classes\Event` erbt von den Contracts.** `Symfony\Component\EventDispatcher\Event` gibt es
in Symfony 7 nicht mehr — die Klasse ist mit Symfony 5 in die Contracts gewandert und in 6 aus
der Komponente verschwunden. Ein Namenswechsel, mehr nicht.

**Die Argumentreihenfolge von `dispatch()` hat sich gedreht — an 21 Stellen.** Symfony 4.3 hat
sie von `dispatch($name, $event)` auf `dispatch($event, $name)` getauscht; der Baum stand noch
auf der alten. Betroffen sind alle `pim.controller.*`-, `pim.entity.*`- und
`pim.file.*`-Ereignisse. Sie werden unverändert in derselben Staffelung verteilt, vom
spezifischsten zum allgemeinsten.

### Der Nothelfer ist weg, und zwar gemessen

`Symfony\Component\Debug\ErrorHandler::register()` und die Closure an
`ExceptionHandler::setHandler()` sind entfallen. Sie existierten, weil Silex' Kette einen
Nicht-`Exception` nicht annahm; Symfonys Kernel schickt mit `handleAllThrowables: true` jeden
Throwable durch `kernel.exception`. Damit hat die Closure keinen Fall mehr, in dem sie
einspringen könnte. Das Paket `symfony/debug`, aus dem beide Klassen stammen, ist mit
`009-002-0001` ohnehin aus dem Baum — ein Nachbau mit `symfony/error-handler` wäre eine Mechanik
ohne Anlass.

`$app['request']` ist mit entfallen. Der Schlüssel lieferte den aktuellen Request aus dem Stack
und war genau deshalb eine Falle: Der Container merkt sich Factory-Ergebnisse, hätte also ab dem
ersten Zugriff **denselben** Request geliefert. Unter Pimple war es dasselbe, und es hat den
Fehlerhandler aus `000-000-0006` sterben lassen. Gelesen hat ihn zuletzt niemand mehr.

### Ein Fund am Rande

**Der OPTIONS-Catch-All stand zweimal im Bootstrap, wortgleich.** Unter Silex war die zweite
Registrierung folgenlos — sie überschrieb die erste. Beim Umstellen fiel sie auf: Zwei Einträge
in einer `RouteCollection`, von denen der zweite nie erreicht wird. Einer ist entfernt.

### Die Reihenfolge ist nachgewiesen, nicht geraten

`tests/Unit/Kernel/HookReihenfolgeTest.php`, fünf Tests. Sie registrieren Hooks in einer
Reihenfolge und erwarten sie in einer anderen — wäre die Priorität wirkungslos, kämen sie in
der Registrierungsreihenfolge und der Test wäre rot. Geprüft sind: höhere Priorität läuft
früher, gleiche Priorität läuft in Registrierungsreihenfolge, ein before-Hook mit `Response`
bricht ab, der Hook bekommt `(Request, Application)`, und after-Hooks sehen die Antwort.

### Nachweis

| | |
|---|---|
| `HookReihenfolgeTest` | `OK (5 tests, 8 assertions)` |
| TypeError über HTTP, vorher | 500, Rumpf **0 Byte** |
| TypeError über HTTP, nachher | 500, JSON mit `message`, `type`, `status` |
| `FehlerantwortApiTest` | grün |
| Volle Suite | 261 Tests, 1 Failure — der Wächter aus `009-001-0005`, dessen Ausnahmeliste `009-002-0006` leert |
