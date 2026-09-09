---
id: 009-002-0000
title: Der Kernel-Schnitt — Symfony 7.4 statt Silex
status: done
depends_on: [009-001-0000, 009-005-0000]
---

# Der Kernel-Schnitt — Symfony 7.4 statt Silex

## Goal
Der Tausch selbst. Nach dieser Story bootet das Framework über einen Symfony-7.4-Kernel,
`silex/silex`, `pimple/pimple` und `knplabs/console-service-provider` sind aus `composer.lock`
verschwunden, und die Suite aus Epic `008` ist grün — **ohne inhaltliche Änderung**.

**Blockiert gewesen und wieder frei, sobald `009-005` steht.** Der erste Versuch von
`009-002-0001` scheiterte sofort an einem harten Konflikt: `symfony/http-foundation` verträgt
sich seit v7.1.7 nicht mit `doctrine/dbal <3.6`. Der Kernel-Wechsel erzwingt also DBAL 3 — was
die Abgrenzung des Epics ausgeschlossen hatte. Der Schritt ist zu `009-005` geworden und läuft
vorher, weil er sich als einziger Teil des Umbaus mit der vollen grünen Suite prüfen lässt.

**Diese Story ist bewusst eine und nicht fünf.** Silex 2.3 fordert die Symfony-Komponenten auf
`^4.0`; ein Zwischenstand, in dem Routing schon auf 7.4 und Middleware noch auf Silex läuft,
existiert nicht. Ein feinerer Schnitt in eigenständige Stories würde bei jedem `/done` einen
roten Stand nach `master` mergen. Die Tasks sind je ein Commit auf dem Story-Branch; grün ist
der Branch als Ganzes.

Was in ihr steckt, in dieser Reihenfolge:

- **Manifest und Container.** Symfony-7.4-Komponenten statt Silex, DI-Container statt Pimple,
  und der `ArrayAccess`-Bridge, der die bestehenden `$app['…']`-Schlüssel am Leben hält —
  einschließlich der Factory-Closures, die `custom/app.php` registriert.
- **Routing.** `RouteManager::mount()` und `bindRoutes()` bleiben als Schnittstelle; darunter
  Symfony Routing. `Route::$isSecure` überlebt als Authentifizierungsentscheidung pro Route.
- **Middleware und Fehler.** before/after zu `kernel.request`/`kernel.response`-Listenern, in
  **nachgewiesener** Reihenfolge, nicht in geratener. `$app->error()` zu einem
  Exception-Listener, der die Antwort aus `000-000-0006` unverändert ausgibt.
- **Console.** Symfony Console 7 ohne `knplabs/console-service-provider`; `ConsoleManager` und
  `bin/console.php` funktional. Dabei zu messen: ob die DBAL-2-Console-Commands unter Console 7
  noch laufen.
- **Einstiegspunkte.** `index.php`, `bootstrap.php`, `bootstrap-web.php`, `tests/router.php`.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 009-002-0001 — Das Manifest auf Symfony 7.4 umstellen
- [x] 009-002-0002 — Container und Anwendung ohne Pimple
- [x] 009-002-0003 — Routing auf Symfony Routing
- [x] 009-002-0004 — Middleware und Fehlerbehandlung als Listener
- [x] 009-002-0005 — Console ohne knplabs
- [x] 009-002-0006 — Die Einstiegspunkte und der Nachweis

## Ergebnis

**Silex ist weg. Das Framework läuft auf einem Symfony-7.4-Kernel, und die Suite aus Epic `008`
ist grün — `OK (266 tests, 640 assertions)`, 0 übersprungen, ohne eine einzige inhaltlich
geänderte Zusicherung.**

| | vorher | nachher |
|---|---|---|
| Pakete | 78 | 71 |
| `symfony/*` | 4.4 / 5.4 gemischt | durchgehend 7.4 |
| Ignorierte CVEs | 5 | **0** |
| Deprecations im Serverlog | 140 Zeilen, 1 Ausnahme | **0 Zeilen, 0 Ausnahmen** |
| Tests | 249 | 266 (+12 Container, +5 Hook-Reihenfolge) |

Kein bestehender Test ist verschwunden oder umgeschrieben. Die einzigen Änderungen an
Testdateien waren zwei Importe.

### Was gebaut wurde

| Klasse | Ersetzt |
|---|---|
| `Kernel\Container` | Pimple — String-Schlüssel, faule Factories, `extend()` samt Einfrieren |
| `Kernel\Application` | `Silex\Application` — jetzt auf `HttpKernel`, `RouterListener`, zwei Resolvern |
| `Kernel\Console`, `…\ConsoleInitEvent` | `knplabs/console-service-provider` |
| `Kernel\Routing\Routensammlung`, `…\Routeneintrag` | `$app['controllers_factory']` |
| `Kernel\Routing\ControllerResolver` | `ServiceControllerServiceProvider` |
| `Kernel\Routing\AbsicherungListener` | der `before()`-Filter am Silex-Controller |

Die Doctrine-Verbindung ist selbst gebaut; `symfony/validator` und `symfony/translation` sind
ersatzlos entfallen, weil sie nachweislich niemand benutzt hat.

### Der wichtigste Befund

**Symfonys `HttpKernel` fängt in der Vorgabe keine `\Error`.** `handleAllThrowables` steht auf
`false`; ein `TypeError` fällt durch den ganzen Kernel, und der Aufrufer bekommt eine leere 500
— gemessen 0 Byte Rumpf.

Das ist **exakt der Befund aus `000-000-0006`**, nur mit Symfonys Kernel statt mit Silex'
`ExceptionListenerWrapper`. Dieselbe Ursache, dieselbe Wirkung, anderes Vorzeichen: damals ein
typisiertes `\Exception` in fremdem Code, hier ein Vorgabewert.

**Ohne den Test, der aus `000-000-0006` stammt, wäre das durchgerutscht.** Die Antwort ist ein
500, und ein Test, der nur den Statuscode prüft, wäre grün geblieben. Genau dafür wurde
`FehlerantwortApiTest` geschrieben — er prüft, dass der Rumpf JSON der Anwendung ist und nicht
HTML.

### Fünf weitere Stellen, an denen Symfony 7 enger ist als Symfony 4

Alle fünf sind beim Laden oder beim ersten Aufruf sofort aufgefallen — keine hat sich
versteckt:

1. **`dispatch()` hat die umgekehrte Argumentreihenfolge**, seit Symfony 4.3. 21 Stellen.
2. **`Symfony\Component\EventDispatcher\Event` gibt es nicht mehr** — die Klasse ist in die
   Contracts gewandert.
3. **`Command::setName()` ist `(string $name): static`**, `configure()` ist `: void`,
   `execute()` ist `: int`.
4. **`SetupCommand::execute()` hatte gar kein `return`.** Unter Console 4 ergab das still
   `null`, was Symfony als 0 las.
5. **`RouteCollection::get()` heisst schon anders** — es holt eine Route beim Namen, während die
   Provider mit `get()` das HTTP-Verb meinen. Deshalb erbt `Routensammlung` nicht davon, sondern
   enthält eine.

### Zwei Dinge, die der Schnitt sichtbar gemacht hat

**Der OPTIONS-Catch-All stand zweimal wortgleich im Bootstrap.** Unter Silex war das folgenlos —
die zweite Registrierung überschrieb die erste. Als `RouteCollection` wären es zwei Einträge, von
denen der zweite nie erreicht wird.

**`$app['request']` war eine Falle.** Ein Container-Schlüssel, der den aktuellen Request aus dem
Stack liest und dessen Ergebnis der Container sich merkt, liefert ab dem ersten Zugriff denselben
Request. Unter Pimple war es dasselbe, und es hat den Fehlerhandler aus `000-000-0006` sterben
lassen. Der Schlüssel ist entfallen; gelesen hat ihn zuletzt niemand mehr.

### Ein Kriterium hat als Stolperdraht funktioniert

`009-002-0002` verlangte, dass `ApplicationInterface` Wort für Wort unverändert bleibt. Ein
typisiertes `extend()` im Container erfüllte die untypisierte Signatur nicht, und PHP hat es
sofort abgelehnt. Die Schnittstelle nachzuziehen wäre technisch harmlos gewesen — jeder Aufrufer
übergibt ohnehin dieselben Typen. **Sie ist trotzdem unverändert geblieben, und der Container hat
die Typangaben abgegeben:** Ein Stolperdraht, den man beim ersten Widerstand entschärft, ist
keiner mehr.

### Ein Fehler von mir, festgehalten statt bereinigt

Der Commit zu `009-002-0004` trägt auch den Code von `009-002-0005` — ich habe
`git add -- an_project lib tests` benutzt, und das nahm die schon geschriebene Console-Arbeit
mit. „Ein Commit je Task" ist für dieses Paar verletzt. Nicht per `--amend` korrigiert: Die
Konventionen verbieten das Umschreiben von Historie, und ein Fehler gehört sichtbar. Der Commit
zu `0005` trägt nur noch Status und Changelog, mit dem Vermerk.

### Was diese Story bewusst nicht getan hat

Keine Bundle-Struktur, kein Doctrine-Umbau über DBAL 3 hinaus, keine Änderung am
Antwort-Envelope, keine an der Authentifizierung. Die Dokumentation und die Vorlage sind
`009-004`; das Deprecation-Gate ist mit `009-003` bereits erfüllt, seine Story kann das
bestätigen statt es herzustellen.
