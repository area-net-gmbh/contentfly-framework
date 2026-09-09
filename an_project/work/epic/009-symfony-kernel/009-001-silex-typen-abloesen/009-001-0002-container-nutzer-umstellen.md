---
id: 009-001-0002
title: Die reinen Container-Nutzer auf die Schnittstelle umstellen
status: done
depends_on: [009-001-0001]
---

# Die reinen Container-Nutzer auf die Schnittstelle umstellen

## Context
Der grösste und einfachste Teil: siebzehn Dateien nennen `Silex\Application` ausschliesslich als
Typangabe und benutzen davon nichts als `$app['schlüssel']`. Für sie ist der Wechsel eine
Zeile — `use`-Anweisung und Typ.

Betroffen sind `Classes\Api`, `Auth`, `Controller\BaseController`, `I18nPermission`, `Mailer`,
`Manager`, `Manager\LoginManager`, `Manager\TypeManager`, `Permission`, `Plugin`, `Type`,
`Controller\ApiController`, `Controller\AuthController`, `Entity\Serializable`, `Entity\User`,
`custom/Command/ExampleCommand` sowie die drei Unit-Tests unter `tests/Unit/Manager/`.

`ApiController` bleibt in diesem Task **nicht** vollständig: Er ruft zusätzlich `handle()` für
zwei interne Sub-Requests. Die Typangabe wechselt hier trotzdem schon, weil die Schnittstelle
`handle()` mitbringt.

## Acceptance criteria
- [x] Keine dieser Dateien nennt noch `Silex\` oder `Pimple\`.
- [x] Die drei Unit-Tests unter `tests/Unit/Manager/` bauen ihr Testobjekt über die neue Klasse.
      Der Kommentar in `RouteAndConsoleManagerTest`, der Pimples `FrozenServiceException`
      erklärt, bleibt sachlich richtig oder wird richtiggestellt — nicht gelöscht.
- [x] `custom/Command/ExampleCommand.php` ist mit umgestellt. Die in `technical.md`
      festgehaltene Inkonsistenz (erbt von `Symfony\…\Command`, wird deshalb nicht registriert)
      wird hier **nicht** gelöst — sie gehört zu `009-004`.
- [x] Kein Verhalten ändert sich.

## Verification
`./vendor/bin/phpunit` grün, unverändert 247 Tests. Dazu eine Zählung: `Silex\`/`Pimple\` kommt
in diesen Dateien nicht mehr vor.

## Ergebnis

Achtzehn Dateien umgestellt — die siebzehn aus dem Task plus
`tests/Unit/Manager/RouteAndConsoleManagerTest.php`, das eigentlich zu `009-001-0003` gehört,
aber sofort brach: Sein `SpionApplication extends Silex\Application` erfüllte die neue
Schnittstelle nicht mehr, sobald `Manager::__construct()` gegen sie type-hintet.

### Der Task hatte eine falsche Voraussetzung, und die Suite hat sie widerlegt

„Siebzehn Dateien nennen `Silex\Application` ausschliesslich als Typangabe" — das stimmt für
den *Namen*, aber nicht für die *Wirkung*. Nach der Umstellung standen **53 Failures und 3
Errors**, und die Ursache war weder eine Datenbank noch ein Cache:

```
Controller "Areanet\PIM\Controller\ApiController::insertAction()" requires that you
provide a value for the "$app" argument.
```

Sieben Actions im `ApiController` nehmen `$app` als **Argument** entgegen, und geliefert wird
es von `Silex\AppArgumentValueResolver`. Der prüft:

```php
Application::class === $argument->getType() || is_subclass_of($argument->getType(), Application::class)
```

— gegen die **konkrete** `Silex\Application`. Eine Schnittstelle besteht diesen Test nicht.
Die Typangabe war also nicht bloss Dokumentation, sondern der Anschluss an einen
Injektionsmechanismus.

### Die Entscheidung: Parameter weg statt Typ zurück

Zwei Wege standen offen. Auf die **konkrete** `Kernel\Application` zeigen — dann greift der
Resolver weiter, und `009-002` muss einen eigenen nachbauen. Oder den Parameter **streichen**:
`$this->app` ist dasselbe Objekt, gesetzt in `BaseController::__construct()` aus derselben
Container-Factory. Gewählt ist das Streichen.

Der Grund ist der Zweck dieser Story: Sie soll verkleinern, was der Schnitt nachbauen muss. Ein
Injektionsmechanismus, dessen einzige Implementierung aus Silex kommt und der nichts liefert,
was der Controller nicht ohnehin hat, ist genau so ein Posten. Sieben Signaturen kürzer, zwölf
Verwendungen auf `$this->app`, und `009-002` braucht keinen Argument-Resolver für die Anwendung.

`ApiController` nennt damit weder `Silex\` noch die neue Schnittstelle — er braucht sie nicht
mehr.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (247 tests, 614 assertions)`, 0 übersprungen |
| Zwischenstand | 53 Failures + 3 Errors, bevor der Argument-Resolver-Befund gelöst war |
| `Silex\`/`Pimple\` | in diesen 18 Dateien nicht mehr vorhanden |
