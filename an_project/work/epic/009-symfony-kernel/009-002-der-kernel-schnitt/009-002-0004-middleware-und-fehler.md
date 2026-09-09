---
id: 009-002-0004
title: Middleware und Fehlerbehandlung als Listener
status: todo
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
- [ ] `before()`, `after()` und `error()` bleiben als Aufrufe erhalten, samt Prioritätsargument;
      `custom/app.php` läuft unverändert.
- [ ] Ein Test belegt die **effektive** Ausführungsreihenfolge mehrerer Hooks mit Prioritäten —
      nicht die Registrierungsreihenfolge.
- [ ] Ein before-Hook, der eine Response zurückgibt, bricht die Verarbeitung ab. Das ist der
      dokumentierte Weg, einen Request zu blockieren (`custom/app.php`).
- [ ] Die vier Punkte aus `000-000-0006` gelten unverändert; `FehlerantwortApiTest` bleibt grün.
- [ ] Die `pim.controller.before.*`-Ereignisse werden weiter in derselben Staffelung verteilt —
      drei Ebenen, vom spezifischsten zum allgemeinsten.
- [ ] CORS-Header und die Security-Header stehen auf jeder Antwort wie bisher.
- [ ] Über den Nothelfer ist entschieden: entweder gemessen überflüssig und entfernt, oder
      behalten mit Begründung.

## Verification
`FehlerantwortApiTest`, `AuthApiTest`, `RouteSecurityApiTest` und `LogSideEffectApiTest` grün —
der letzte prüft die Hook-Staffelung. Dazu ein Aufruf mit einem absichtlich ausgelösten
PHP-Fehler: Er muss als JSON der Anwendung ankommen, nicht als HTML.
