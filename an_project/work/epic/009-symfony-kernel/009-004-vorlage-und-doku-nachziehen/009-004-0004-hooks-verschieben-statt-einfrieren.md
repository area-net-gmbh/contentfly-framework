---
id: 009-004-0004
title: before/after/error dürfen den Dispatcher nicht einfrieren
status: done
depends_on: []
---

# before/after/error dürfen den Dispatcher nicht einfrieren

## Context
Gefunden in `009-004-0001`, und zwar dadurch, dass die Vorlage zum ersten Mal ihren eigenen
dokumentierten Weg geht: `custom/app.php` registriert einen Projekt-Command über
`$app['consoleManager']->addCommand(…)`. Das schlägt fehl:

```
RuntimeException: Der Dienst "dispatcher" ist bereits ausgelesen und laesst sich nicht
mehr erweitern.
```

Die Ursache steht drei Zeilen weiter oben in derselben Datei: `custom/app.php` registriert vor
dem Command einen `before()`-Hook. `Kernel\Application::before()` greift direkt auf
`$this['dispatcher']` zu — damit ist der Dienst ausgelesen und eingefroren, und der
`ConsoleManager`, der ihn über `extend()` erweitern muss, kommt zu spät.

**Silex hatte dasselbe Problem und hat es gelöst.** `Silex\Application::on()`:

```php
if ($this->booted) {
    $this['dispatcher']->addListener($eventName, $callback, $priority);
    return;
}
$this->extend('dispatcher', function ($dispatcher, $app) use (…) {
    $dispatcher->addListener($eventName, $callback, $priority);
    return $dispatcher;
});
```

Solange die Anwendung nicht gebootet ist, wird die Registrierung **verschoben** statt
ausgeführt. Deshalb fror `before()` dort nichts ein. Beim Nachbau in `009-002-0002` ist das
verlorengegangen — die Methode sah einfacher aus, und der Fall, in dem es auffällt, war nicht
abgedeckt: Kein Test registrierte einen Command **nach** einem Hook.

## Acceptance criteria
- [x] `before()`, `after()`, `error()` und `on()` verschieben die Registrierung, solange die
      Anwendung nicht gebootet ist — wie Silex es tat.
- [x] Die Ausführungsreihenfolge bleibt unverändert; `HookReihenfolgeTest` bleibt grün, ohne
      dass eine Zusicherung angepasst wird.
- [x] Ein Test deckt genau die Abfolge ab, die den Fehler ausgelöst hat: erst ein `before()`,
      dann ein `addCommand()`. Er läuft ohne Datenbank.
- [x] Das Einfrieren selbst bleibt bestehen. Es ist keine Schikane, sondern die Bedingung, die
      `RouteAndConsoleManagerTest` seit `008-004` festhält — nur darf das Framework nicht selbst
      dagegenlaufen.

## Verification
`php bin/console.php list` zeigt den Beispiel-Command der Vorlage. Unit-Suite grün, volle Suite
grün.

## Ergebnis

**`on()` verschiebt die Registrierung, solange die Anwendung nicht gebootet ist — wie Silex es
tat.** `before()`, `after()` und `error()` laufen jetzt über `on()` statt selbst auf den
Dispatcher zuzugreifen.

Die Reihenfolge in `boot()` ist dabei entscheidend: `gebootet = true` steht **vor** dem ersten
Zugriff auf den Dispatcher. Andersherum riefe dieser Zugriff `on()` rekursiv.

### Warum es niemandem aufgefallen ist

Weil die Vorlage ihren eigenen dokumentierten Weg nie gegangen ist. `custom/app.php` beschreibt
seit jeher beides — Middleware **und** Console-Commands —, aber der Beispiel-Command war nicht
registriert (die Inkonsistenz aus Epic `012`). Damit gab es im ganzen Baum keine Stelle, an der
ein `before()` und ein `addCommand()` in dieser Reihenfolge zusammentrafen.

`009-004-0001` hat den Command registriert, und der Fehler kam sofort. **Das ist der eigentliche
Wert dieser Story:** Eine Vorlage, die ihren eigenen Weg nicht geht, ist keine Vorlage, sondern
eine Behauptung.

### Das Einfrieren bleibt

Es ist keine Schikane. `RouteAndConsoleManagerTest` hält seit `008-004` fest, dass `extend()`
nach dem ersten Zugriff wirft, und `000-000-0006` ist genau darüber gestolpert. Ein Container,
der das stillschweigend erlaubte, würde einen echten Reihenfolgefehler verstecken. Was nicht
sein darf, ist, dass das **Framework selbst** dagegenläuft — und genau das tat es.

### Nachweis, beide Richtungen

| Probe | Ergebnis |
|---|---|
| Neuer Test `testEinHookVerhindertKeineSpaetereCommandRegistrierung` | grün |
| Derselbe Test gegen die alte Fassung von `on()` | rot, mit genau der `RuntimeException` |
| `HookReihenfolgeTest` insgesamt | `OK (6 tests, 9 assertions)` — keine Zusicherung angepasst |
| `php bin/console.php list` | zeigt `custom:example:command:run` |
| Volle Suite | `OK (267 tests, 640 assertions)` |
