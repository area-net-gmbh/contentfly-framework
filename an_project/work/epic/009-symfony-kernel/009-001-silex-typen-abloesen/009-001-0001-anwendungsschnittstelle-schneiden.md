---
id: 009-001-0001
title: Die Anwendungsschnittstelle schneiden und dazwischenlegen
status: done
depends_on: []
---

# Die Anwendungsschnittstelle schneiden und dazwischenlegen

## Context
Der erste und einzige strukturelle Schritt der Story: eine eigene Schnittstelle, die genau die
Oberfläche beschreibt, die das Framework von seiner Anwendung tatsächlich benutzt — und eine
Klasse, die sie heute über Silex bedient und morgen über Symfony. Alle weiteren Tasks tauschen
nur noch Typangaben gegen diese Schnittstelle.

Gemessen wird die Oberfläche, nicht geschätzt. Ausserhalb des Bootstraps benutzt der eigene Code
vom `$app`-Objekt genau das hier:

| Aufruf | Wo | Herkunft |
|---|---|---|
| `$app['schlüssel']`, `$app['x'] = …` | überall | `ArrayAccess` (Pimple) |
| `mount()` | `RouteManager` | Silex |
| `extend()` | `ConsoleManager` | Pimple |
| `before()`, `after()` | `BaseControllerProvider`, `custom/app.php` | Silex |
| `handle()` | `ApiController`, 2× Sub-Request | `HttpKernelInterface` (Symfony) |
| `redirect()`, `stream()` | `FileController` | Silex, beides nur Response-Fabriken |
| `register()` | `InstallCommand` | Silex |

Der Bootstrap benutzt zusätzlich `error()`, `json()`, `run()`, `options()` und `get()`. Diese
fünf bleiben vorerst dort, wo sie sind — der Bootstrap ist die eine Stelle, die den Kernel
kennen *darf*.

## Acceptance criteria
- [x] Es gibt `Areanet\PIM\Classes\Kernel\ApplicationInterface`, die die oben gemessene
      Oberfläche deklariert — nicht mehr. Jede Methode trägt eine Begründung, warum sie drin
      ist; `handle()` erbt aus Symfonys `HttpKernelInterface` statt neu erfunden zu werden.
- [x] Es gibt `Areanet\PIM\Classes\Kernel\Application`, die heute `Silex\Application` erweitert
      und die Schnittstelle implementiert. Der Bootstrap instanziiert sie statt
      `Silex\Application`.
- [x] Am Klassenkommentar steht, was beim Kernel-Schnitt (`009-002`) mit dieser Klasse passiert:
      Die Vererbung fällt weg, die Schnittstelle bleibt, und kein Aufrufer merkt es.
- [x] Kein Verhalten ändert sich. Die Suite läuft unverändert durch.

## Verification
`./vendor/bin/phpunit` gegen eine installierte Instanz: 247 Tests grün, 0 übersprungen. Dazu
`php bin/console.php list` und ein HTTP-Aufruf gegen `/api/config` — die beiden Einstiegspunkte,
die das neue Objekt zuerst anfassen.

## Ergebnis

`Areanet\PIM\Classes\Kernel\ApplicationInterface` und `…\Kernel\Application` stehen, der
Bootstrap instanziiert die neue Klasse. Der Rumpf der Klasse ist leer und soll es bleiben — jede
Methode, die dort entstünde, müsste `009-002` zusätzlich nachbauen.

### Zwei Entscheidungen an der Schnittstelle

**`register()` ist nicht aufgenommen, obwohl es in der gemessenen Oberfläche steht.**
`InstallCommand::bootDoctrine()` ruft es. Sein Parametertyp ist aber
`Pimple\ServiceProviderInterface` — eine Schnittstelle, die Pimple aus dem Code nehmen soll und
Pimple in der eigenen Signatur trägt, verfehlt ihren Zweck. Der Aufruf bleibt als benannte
Ausnahme stehen; er registriert Silex' eigenen `DoctrineServiceProvider` und wird in `009-002`
ohnehin ersetzt.

**`redirect()` und `stream()` stehen vorübergehend drin.** `009-001-0004` ersetzt ihre drei
Aufrufstellen durch die Symfony-Klassen, die sie ohnehin zurückgeben, und nimmt beide danach
wieder heraus. Bis dahin wäre die Schnittstelle sonst unvollständig, und `FileController` würde
gegen etwas type-hinten, das sie nicht zusichert.

### Eine Falle beim Bauen

Ich hatte `redirect()` und `stream()` zuerst mit Rückgabetypen deklariert — `: RedirectResponse`
und `: StreamedResponse`, denn genau das geben sie zurück. Das ist nicht erfüllbar:
`Silex\Application` deklariert für beide **keinen** Rückgabetyp, und eine Implementierung ohne
Rückgabetyp erfüllt keine Signatur mit einem. Die Angabe steht jetzt im `@return`, nicht in der
Signatur, mit dem Grund daneben.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (247 tests, 614 assertions)`, 0 übersprungen — unverändert zur Grundlinie |
| `instanceof` | Die neue Klasse erfüllt `ApplicationInterface`, `ArrayAccess` und `HttpKernelInterface` |
| Console | `php bin/console.php list` läuft |
| HTTP | `GET /api/config` antwortet mit 200 |
