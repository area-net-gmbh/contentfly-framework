---
id: 009-001-0000
title: Den eigenen Code von den Silex-Typen lösen
status: in-progress
depends_on: []
---

# Den eigenen Code von den Silex-Typen lösen

## Goal
Nach dieser Story nennt der eigene Code `Silex\` und `Pimple\` nur noch dort, wo der Bootstrap
den Kernel selbst aufbaut. Alles andere — Controller, Provider, Manager, Types, Entities —
spricht gegen eigene Schnittstellen.

**Das läuft weiterhin auf Silex, und die Suite bleibt grün.** Das ist der Zweck: Es gibt keinen
Zustand, in dem beide Kernel nebeneinander liegen (siehe Epic), also muss alles, was sich ohne
den Tausch erledigen lässt, vorher erledigt sein. Je weniger im Schnitt selbst passiert, desto
eindeutiger ist eine Abweichung der Suite danach zuzuordnen.

Heute nennen **28 Dateien** `Silex\` oder `Pimple\`. Die drei Muster dahinter:

1. `Silex\Application` als Typ im Konstruktor oder in der Signatur — `BaseController`,
   `Classes\Type`, `Manager`, `Api`, `Auth`, `Permission`, `I18nPermission`, `Mailer`,
   `LoginManager`, `TypeManager`, `Plugin`, `Serializable`, `User`.
2. `Silex\Api\ControllerProviderInterface` und `$app['controllers_factory']` in den fünf
   Controller-Providern.
3. `$app->extend('dispatcher', …)` im `ConsoleManager` und im Bootstrap — Pimples
   Erweiterungsmechanismus, den der Symfony-Container so nicht kennt.

Was hier **nicht** passiert: Der Container-Zugriff `$app['schlüssel']` bleibt genau so stehen.
Er ist ein zugesichertes API für Bestandsprojekte (`custom/app.php` registriert Services als
`$app['key'] = function ($app) { … }`), und der Bridge dafür entsteht im Schnitt.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 009-001-0001 — Die Anwendungsschnittstelle schneiden und dazwischenlegen
- [ ] 009-001-0002 — Die reinen Container-Nutzer auf die Schnittstelle umstellen
- [ ] 009-001-0003 — Controller-Provider und Manager von Silex lösen
- [ ] 009-001-0004 — Die Silex-Hilfsmethoden in den Controllern ablösen
- [ ] 009-001-0006 — Die Console-Commands von knplabs lösen
- [ ] 009-001-0005 — Ein Wächter gegen die Rückkehr der Silex-Typen
