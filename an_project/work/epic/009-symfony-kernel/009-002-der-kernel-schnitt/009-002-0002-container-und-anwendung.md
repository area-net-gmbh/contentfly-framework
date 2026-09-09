---
id: 009-002-0002
title: Container und Anwendung ohne Pimple
status: review
depends_on: [009-002-0001]
---

# Container und Anwendung ohne Pimple

## Context
`Kernel\Application` verliert die Vererbung von `Silex\Application`. Was an ihre Stelle tritt,
muss zwei Dinge gleichzeitig können: den HTTP-Kernel bedienen und den Container-Vertrag halten,
an dem Bestandsprojekte hängen.

**Der Container-Vertrag ist Pimples, nicht Symfonys.** `custom/app.php` registriert Dienste als

```php
$app['meine.service'] = function ($app) { return new MeinService($app['orm.em']); };
```

— eine **faule Factory**, die erst beim ersten Zugriff läuft. Symfonys DI-Container nimmt zur
Laufzeit nur fertige Objekte entgegen. Entschieden ist deshalb: **ein eigener, kleiner
Container** trägt die String-Schlüssel und die Factories; Symfonys DI-Container kommt dort zum
Einsatz, wo der Kernel ihn selbst braucht. Rund hundert Zeilen, selbst geprüft, und
`custom/app.php` funktioniert unverändert weiter. Entschieden am 2026-09-09.

Das Einfrier-Verhalten gehört mitentschieden: Pimple friert eine Definition ein, sobald sie
einmal ausgelesen wurde, und `extend()` wirft danach. Der `ConsoleManager` hängt an genau
dieser Reihenfolge, und `tests/Unit/Manager/RouteAndConsoleManagerTest.php` prüft sie.

## Was aus den vier `register()`-Aufrufen wird
| Aufruf | Was daraus wird |
|---|---|
| `ServiceControllerServiceProvider` | entfällt — er erlaubte `"dienst:methode"` als Controller; das übernimmt das Routing in `009-002-0003` |
| `DoctrineServiceProvider` | selbst gebaut. `$app['database']` baut die Verbindung an anderer Stelle bereits über `DriverManager`; `$app['dbs']['pim']` und `$app['db']` brauchen dieselbe |
| `ValidatorServiceProvider` | entfällt ersatzlos (`009-002-0001`) |
| `ConsoleServiceProvider` | `009-002-0005` |

## Acceptance criteria
- [x] `Kernel\ApplicationInterface` ist **Wort für Wort unverändert**. Ändert sich dort etwas,
      war die Schnittstelle aus `009-001` falsch geschnitten — dann gehört das begründet, nicht
      nachgezogen.
- [x] Der eigene Container hält: Lesen, Setzen, faule Factory mit `$app` als Argument,
      `isset()`, `unset()`, und `extend()` samt Einfrier-Verhalten.
- [x] Ein Unit-Test prüft den Container gegen genau diese Zusagen, ohne Datenbank.
- [x] `Kernel\Application` implementiert `HttpKernelInterface` über Symfonys `HttpKernel` und
      nennt Silex nicht mehr.
- [x] Die Doctrine-Verbindung steht ohne Silex-Provider; `$app['db']`, `$app['dbs']` und
      `$app['orm.em']` liefern dasselbe wie vorher, `DB_PORT` eingeschlossen (`000-000-0004`).
- [x] `InstallCommand::bootDoctrine()` läuft ohne `Silex\Provider\DoctrineServiceProvider` —
      die letzte Ausnahme in der Liste des Wächters, die nicht der Bootstrap ist.

## Verification
`./vendor/bin/phpunit --testsuite unit` grün, einschliesslich der neuen Container-Tests. Dazu
eine Probe, die zeigt, dass eine Factory aus `custom/app.php` erst beim Zugriff läuft und
`$app` als Argument bekommt.

## Ergebnis

**`Kernel\ApplicationInterface` ist Wort für Wort unverändert geblieben.** Das Kriterium war als
Stolperdraht gedacht, und es hat einmal ausgelöst — dazu unten.

### Der Container

`Kernel\Container` bildet Pimples Vertrag nach: String-Schlüssel, faule Factories mit dem
Container als Argument, `isset`/`unset`, `extend()` und das Einfrieren. **Zwölf Unit-Tests
prüfen genau die Zusagen, wegen derer er geschrieben wurde** — nicht seine Interna, sondern das,
woran `custom/app.php` hängt.

Bewusst **nicht** nachgebaut: `share()`, `protect()`, `raw()`, `factory()`, `register()`. Sie
kommen im Baum nicht vor. Was fehlt, kommt dazu, wenn ein Aufrufer es braucht, nicht auf Verdacht.

**Das Einfrieren ist nachgebaut, weil eine Zusicherung daran hängt.** Sobald eine Definition
ausgelesen wurde, wirft `extend()`. Der `ConsoleManager` muss den Dispatcher erweitern, bevor
ihn jemand anfasst; `000-000-0006` ist genau darüber gestolpert, und
`RouteAndConsoleManagerTest` hält es seit `008-004` fest. Ein Container, der das stillschweigend
erlaubte, würde den Fehler verstecken statt ihn zu melden.

Zwei Feinheiten stehen als Kommentar an der Stelle: Ein Eintrag mit dem Wert `null` gilt als
vorhanden — `bootstrap.php` legt `$app['auth.user'] = null` an, und die Unterscheidung „nicht
registriert" gegen „noch niemand angemeldet" darf nicht verlorengehen. Und ein unbekannter
Schlüssel wirft, statt `null` zu liefern: Sonst würde ein Tippfehler zu einem stillen Fehler
weit später.

### Der Stolperdraht hat ausgelöst

Ich hatte `Container::extend(string $id, callable $callable): void` typisiert. Das erfüllt
`ApplicationInterface::extend($id, $callable)` **nicht** — PHP erlaubt einer Implementierung
nicht, Parametertypen hinzuzufügen. Der Fehler kam sofort und unmissverständlich:

```
Declaration of Container::extend(string $id, callable $callable): void must be
compatible with ApplicationInterface::extend($id, $callable)
```

Die Schnittstelle nachzuziehen wäre technisch harmlos gewesen — jeder Aufrufer übergibt ohnehin
`(string, callable)`. **Trotzdem ist sie unverändert geblieben und der Container hat die
Typangaben abgegeben.** Das Kriterium existiert, um das stille Umformen des Vertrags beim
Kernel-Wechsel zu bemerken; es beim ersten Widerstand zu entschärfen, hiesse es abzuschaffen.
Der Gewinn aus zwei Typangaben wiegt das nicht auf. Sie stehen jetzt im `@param`, mit der
Begründung daneben.

### Die Anwendung

`Kernel\Application` **ist** der Container, wie `Silex\Application` es war — sie erbt von ihm.
Darunter liegen `HttpKernel`, `RouterListener`, `ControllerResolver` und `ArgumentResolver`
statt Silex' Kernel. `before()`, `after()` und `error()` bleiben als Aufrufe erhalten, weil
`custom/app.php` sie benutzt; darunter sind es Listener auf `kernel.request`,
`kernel.response` und `kernel.exception`.

**Ihre Feinheiten gehören `009-002-0004`** — die Reihenfolge mit Prioritäten und die Form der
Fehlerantwort. Hier stehen sie, damit der Kernel überhaupt läuft.

Der Controller-Resolver hängt bewusst als **Dienst** im Container, damit `009-002-0003` ihn
austauschen kann, ohne diese Klasse anzufassen: Dort kommt die Auflösung von `"dienst:methode"`
dazu, die in Silex der `ServiceControllerServiceProvider` geliefert hat.

`boot()` hängt die Routen erst beim ersten `handle()` an — später als im Konstruktor, weil
`mount()` bis unmittelbar vor dem ersten Request aufgerufen wird: `bootstrap.php` liest
`custom/app.php` und ruft danach `bindRoutes()`.

### Doctrine ohne Silex-Provider

`$app['dbs']` und `$app['db']` sind selbst gebaut, mit `DriverManager` — wie es
`$app['database']` weiter unten seit jeher tut. Beide Schlüssel bleiben, weil `bin/console.php`
und `EntityManagerFactory` sie lesen. `InstallCommand::bootDoctrine()` ist mitgezogen; damit
fällt der Eintrag für den Installer aus der Ausnahmeliste des Wächters.

**Dass es zwei Verbindungen gibt** — `$app['db']` für den EntityManager, `$app['database']` für
direktes SQL — ist älter als dieser Task und hier nicht angefasst worden.

### Was hier nicht geprüft werden konnte

`tests/Unit/Manager/RouteAndConsoleManagerTest.php` lässt sich nicht laden: Es zieht
`CustomCommand` und damit `Kernel\Command` herein, das noch von `Knp\Command\Command` erbt —
einem Paket, das `009-002-0001` entfernt hat. Das ist **`009-002-0005`**. Der Test erwartet
ausserdem `Pimple\Exception\FrozenServiceException`, wo der neue Container eine
`\RuntimeException` wirft; auch das wird dort nachgezogen.

Geprüft wurde stattdessen mit `ContainerTest` und einer Probe gegen die laufende Testdatenbank.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `ContainerTest` | `OK (12 tests, 16 assertions)` |
| Factory läuft beim Registrieren | nein — erst beim Zugriff |
| Factory bekommt `$app` | ja, Ergebnis `gebaut mit wert` |
| Zweiter Zugriff | dasselbe Objekt |
| `request_stack`, `dispatcher`, `resolver`, `argument_resolver`, `kernel` | alle Symfony-7-Klassen |
| `$app['dbs']['pim']` | `Doctrine\DBAL\Connection` |
| `$app['db']` === `$app['dbs']['pim']` | ja |
| Port aus der Konfiguration | 3327 — der Befund aus `000-000-0004` hält |
| `SELECT 1` gegen die Testdatenbank | 1 |
| `$app['orm.em']` auf `$app['db']` | ja |
