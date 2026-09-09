---
id: 009-001-0003
title: Controller-Provider und Manager von Silex lösen
status: done
depends_on: [009-001-0001]
---

# Controller-Provider und Manager von Silex lösen

## Context
Die schwierigere Hälfte. Die fünf Controller-Provider implementieren
`Silex\Api\ControllerProviderInterface` — eine Schnittstelle, deren `connect()` ein
`ControllerCollection` von Silex zurückgibt. Das lässt sich nicht durch eine Typangabe
ersetzen, sondern braucht eine eigene Schnittstelle, deren Rückgabewert beim Schnitt in
`009-002` sein Innenleben tauscht.

Dazu zwei Manager mit je einer Silex-eigenen Methode:

- `RouteManager::bindRoutes()` ruft `$this->app->mount()`.
- `ConsoleManager::addCommand()` ruft `$this->app->extend('dispatcher', …)` und hängt einen
  Listener auf `Knp\Console\ConsoleEvents::INIT`. Das ist der Punkt, an dem
  `knplabs/console-service-provider` in den eigenen Code hineinragt — und das Paket geht in
  `009-002` weg.

**Zwei Fallen, beide schon einmal zugeschnappt.** Erstens: Pimple friert einen Service ein,
sobald er einmal ausgelesen wurde; ein `extend()` danach scheitert mit
`FrozenServiceException` — genau das ist bei `000-000-0006` passiert, als ein Listener über
`$app['dispatcher']->addListener()` statt über `$app->on()` registriert wurde. Zweitens: Die
`isSecure`-Entscheidung pro Route liegt in `CustomControllerProvider::connect()` und ist das,
was beim Umschreiben von Routen als Erstes verloren geht.

## Acceptance criteria
- [x] Es gibt eine eigene Provider-Schnittstelle; die fünf Provider implementieren sie statt
      `Silex\Api\ControllerProviderInterface`. Wo der Rückgabetyp heute noch Silex' ist, steht
      an der Stelle, warum das so bleibt, bis `009-002` ihn tauscht.
- [x] `RouteManager` und `ConsoleManager` nennen `Silex\` und `Knp\` nicht mehr; beide Aufrufe
      laufen über die Schnittstelle aus `009-001-0001`.
- [x] `RouteSecurityApiTest` bleibt grün — die `isSecure`-Semantik ist unverändert.
- [x] Der Unit-Test zu `RouteManager`/`ConsoleManager` prüft weiterhin, was er vorher prüfte,
      einschliesslich des Einfrier-Verhaltens oder dessen begründeter Ablösung.

## Verification
`./vendor/bin/phpunit` grün. Zusätzlich ein Aufruf einer gesicherten und einer ungesicherten
Route der Vorlage über HTTP, und `php bin/console.php list` mit einem über den `ConsoleManager`
registrierten Command.

## Ergebnis

### Warum eine eigene Provider-Schnittstelle unvermeidlich war

Silex' `ControllerProviderInterface` schreibt `connect(Silex\Application $app)` vor. PHP erlaubt
einer Implementierung, den Parametertyp zu **erweitern**, nicht ihn zu ersetzen — und
`Silex\Application` erfüllt `ApplicationInterface` nicht. Solange die Provider Silex'
Schnittstelle implementieren, **müssen** sie Silex im Kopf nennen. Es gab also keine Variante,
in der man mit einer geänderten Typangabe davonkommt.

`Areanet\PIM\Classes\Kernel\ControllerProviderInterface` deklariert dieselbe Methode gegen die
eigene Anwendung. **Ohne Rückgabetyp**, und das ist Absicht: `connect()` gibt heute eine
`Silex\ControllerCollection` zurück, gebaut aus `$app['controllers_factory']`. Das ist der eine
Punkt, an dem die Provider den Kernel wirklich brauchen. Einen Rückgabetyp jetzt zu setzen
hiesse, Silex genau dort festzuschreiben, wo er als Nächstes verschwindet.

### Die Folge, die daran hing

`Silex\Application::mount()` erkennt einen Provider daran, dass er **Silex'** Schnittstelle
implementiert, und ruft `connect()` dann selbst. Das tut er jetzt nicht mehr. Also rufen
`RouteManager::bindRoutes()` und `bootstrap-web.php` `connect()` selbst und übergeben die
fertige Sammlung — `mount()` nimmt sie unverändert entgegen. Das Ergebnis ist dasselbe, nur
ohne Silex' Erkennung dazwischen, und `009-002` hat eine Stelle weniger zu ersetzen.

### `ConsoleManager`

Der einzige Grund, warum er `knplabs` überhaupt nannte, war die Typangabe
`Knp\Console\ConsoleEvent` am Ereignis. Gebraucht wird davon `getApplication()`. Die Angabe ist
weggefallen statt gegen eine eigene Attrappe getauscht zu werden, die dasselbe Ereignis nur
anders benennt. Der Ereignisname liegt jetzt als `Kernel\ConsoleEvents::INIT` im eigenen Baum,
mit demselben Wert `'console.init'` — solange das Paket die Console startet, hört sie darauf.

### Eine Lücke in der Messung, aufgefallen beim Nachzählen

Die Oberfläche in `009-001-0001` ist gegen `Silex\` und `Pimple\` gemessen worden — **nicht
gegen `Knp\`**. Dabei ist `knplabs/console-service-provider` genauso ein Paket, das `009-002`
entfernen muss: Es deckelt `symfony/console` auf `^4`.

Übrig sind dadurch fünf Dateien, die `Knp\Command\Command` als **Basisklasse** benutzen:
`Classes\Command\CustomCommand` und die drei Framework-Commands, plus `InstallCommand` mit
`Silex\Provider\DoctrineServiceProvider`. Das ist keine Typangabe, sondern Vererbung — sie
lässt sich nicht durch eine Zeile ersetzen. Aufgenommen als **`009-001-0006`**; hier bewusst
nicht miterledigt, weil ein Task, der unterwegs seinen eigenen Umfang erweitert, hinterher
nicht mehr prüfbar ist.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (247 tests, 614 assertions)`, 0 übersprungen |
| Unit-Suite | `OK (41 tests, 58 assertions)` |
| Ungesicherte Vorlagen-Route ohne Token | 200 |
| Gesicherte Framework-Route ohne / mit Token | 401 / 200 |
| Console | die drei `appcms:`-Commands sind da |
