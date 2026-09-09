---
id: 009-001-0001
title: Die Anwendungsschnittstelle schneiden und dazwischenlegen
status: todo
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
- [ ] Es gibt `Areanet\PIM\Classes\Kernel\ApplicationInterface`, die die oben gemessene
      Oberfläche deklariert — nicht mehr. Jede Methode trägt eine Begründung, warum sie drin
      ist; `handle()` erbt aus Symfonys `HttpKernelInterface` statt neu erfunden zu werden.
- [ ] Es gibt `Areanet\PIM\Classes\Kernel\Application`, die heute `Silex\Application` erweitert
      und die Schnittstelle implementiert. Der Bootstrap instanziiert sie statt
      `Silex\Application`.
- [ ] Am Klassenkommentar steht, was beim Kernel-Schnitt (`009-002`) mit dieser Klasse passiert:
      Die Vererbung fällt weg, die Schnittstelle bleibt, und kein Aufrufer merkt es.
- [ ] Kein Verhalten ändert sich. Die Suite läuft unverändert durch.

## Verification
`./vendor/bin/phpunit` gegen eine installierte Instanz: 247 Tests grün, 0 übersprungen. Dazu
`php bin/console.php list` und ein HTTP-Aufruf gegen `/api/config` — die beiden Einstiegspunkte,
die das neue Objekt zuerst anfassen.
