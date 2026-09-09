---
id: 009-001-0002
title: Die reinen Container-Nutzer auf die Schnittstelle umstellen
status: todo
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
- [ ] Keine dieser Dateien nennt noch `Silex\` oder `Pimple\`.
- [ ] Die drei Unit-Tests unter `tests/Unit/Manager/` bauen ihr Testobjekt über die neue Klasse.
      Der Kommentar in `RouteAndConsoleManagerTest`, der Pimples `FrozenServiceException`
      erklärt, bleibt sachlich richtig oder wird richtiggestellt — nicht gelöscht.
- [ ] `custom/Command/ExampleCommand.php` ist mit umgestellt. Die in `technical.md`
      festgehaltene Inkonsistenz (erbt von `Symfony\…\Command`, wird deshalb nicht registriert)
      wird hier **nicht** gelöst — sie gehört zu `009-004`.
- [ ] Kein Verhalten ändert sich.

## Verification
`./vendor/bin/phpunit` grün, unverändert 247 Tests. Dazu eine Zählung: `Silex\`/`Pimple\` kommt
in diesen Dateien nicht mehr vor.
