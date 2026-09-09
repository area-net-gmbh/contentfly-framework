---
id: 009-001-0003
title: Controller-Provider und Manager von Silex lösen
status: todo
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
- [ ] Es gibt eine eigene Provider-Schnittstelle; die fünf Provider implementieren sie statt
      `Silex\Api\ControllerProviderInterface`. Wo der Rückgabetyp heute noch Silex' ist, steht
      an der Stelle, warum das so bleibt, bis `009-002` ihn tauscht.
- [ ] `RouteManager` und `ConsoleManager` nennen `Silex\` und `Knp\` nicht mehr; beide Aufrufe
      laufen über die Schnittstelle aus `009-001-0001`.
- [ ] `RouteSecurityApiTest` bleibt grün — die `isSecure`-Semantik ist unverändert.
- [ ] Der Unit-Test zu `RouteManager`/`ConsoleManager` prüft weiterhin, was er vorher prüfte,
      einschliesslich des Einfrier-Verhaltens oder dessen begründeter Ablösung.

## Verification
`./vendor/bin/phpunit` grün. Zusätzlich ein Aufruf einer gesicherten und einer ungesicherten
Route der Vorlage über HTTP, und `php bin/console.php list` mit einem über den `ConsoleManager`
registrierten Command.
