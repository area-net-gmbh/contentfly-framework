---
id: 009-002-0002
title: Container und Anwendung ohne Pimple
status: todo
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
- [ ] `Kernel\ApplicationInterface` ist **Wort für Wort unverändert**. Ändert sich dort etwas,
      war die Schnittstelle aus `009-001` falsch geschnitten — dann gehört das begründet, nicht
      nachgezogen.
- [ ] Der eigene Container hält: Lesen, Setzen, faule Factory mit `$app` als Argument,
      `isset()`, `unset()`, und `extend()` samt Einfrier-Verhalten.
- [ ] Ein Unit-Test prüft den Container gegen genau diese Zusagen, ohne Datenbank.
- [ ] `Kernel\Application` implementiert `HttpKernelInterface` über Symfonys `HttpKernel` und
      nennt Silex nicht mehr.
- [ ] Die Doctrine-Verbindung steht ohne Silex-Provider; `$app['db']`, `$app['dbs']` und
      `$app['orm.em']` liefern dasselbe wie vorher, `DB_PORT` eingeschlossen (`000-000-0004`).
- [ ] `InstallCommand::bootDoctrine()` läuft ohne `Silex\Provider\DoctrineServiceProvider` —
      die letzte Ausnahme in der Liste des Wächters, die nicht der Bootstrap ist.

## Verification
`./vendor/bin/phpunit --testsuite unit` grün, einschliesslich der neuen Container-Tests. Dazu
eine Probe, die zeigt, dass eine Factory aus `custom/app.php` erst beim Zugriff läuft und
`$app` als Argument bekommt.
