---
id: 009-001-0000
title: Den eigenen Code von den Silex-Typen lösen
status: done
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
- [x] 009-001-0001 — Die Anwendungsschnittstelle schneiden und dazwischenlegen
- [x] 009-001-0002 — Die reinen Container-Nutzer auf die Schnittstelle umstellen
- [x] 009-001-0003 — Controller-Provider und Manager von Silex lösen
- [x] 009-001-0004 — Die Silex-Hilfsmethoden in den Controllern ablösen
- [x] 009-001-0006 — Die Console-Commands von knplabs lösen
- [x] 009-001-0005 — Ein Wächter gegen die Rückkehr der Silex-Typen

## Ergebnis

**Der eigene Code nennt `Silex\`, `Pimple\` und `Knp\` nur noch an fünf Stellen, und jede
davon ist eine Aufgabe für `009-002`.** Vorher waren es 28 Dateien; die Zahl steht in
`tests/Unit/Kernel/KeineSilexTypenTest.php` als Zusicherung, nicht als Behauptung.

Der Weg dahin war zweimal ein anderer als geplant, und beide Male hat die Messung es gezeigt.

### Die Typangabe war nicht bloss Dokumentation

`009-001-0002` ging von siebzehn Dateien aus, die `Silex\Application` „ausschliesslich als
Typangabe" nennen. Nach der Umstellung standen **53 Failures und 3 Errors**:

```
Controller "…ApiController::insertAction()" requires that you provide a value
for the "$app" argument.
```

`Silex\AppArgumentValueResolver` liefert sieben Actions ihr `$app`-Argument und prüft dafür auf
die **konkrete** `Silex\Application` — eine Schnittstelle besteht diesen Test nicht. Die
Typangabe war der Anschluss an einen Injektionsmechanismus.

Entschieden ist, den Parameter zu **streichen** statt den Typ zurückzunehmen: `$this->app` ist
dasselbe Objekt aus derselben Container-Factory. Ein Injektionsweg, dessen einzige
Implementierung aus Silex kommt und der nichts liefert, was der Controller nicht ohnehin hat,
ist genau der Posten, den diese Story kleiner machen soll.

### Die Messung hatte eine Lücke

Die Oberfläche in `009-001-0001` ist gegen `Silex\` und `Pimple\` gemessen worden — **nicht
gegen `Knp\`**. Dabei muss `knplabs/console-service-provider` genauso weg: Es deckelt
`symfony/console` auf `^4`. Fünf Dateien hingen daran über Vererbung. Aufgefallen ist es beim
Nachzählen in `009-001-0003`, aufgenommen als **`009-001-0006`** statt dort miterledigt — ein
Task, der unterwegs seinen eigenen Umfang erweitert, ist hinterher nicht mehr prüfbar.

### Was gebaut wurde

| | |
|---|---|
| `Kernel\ApplicationInterface` | Die gemessene Oberfläche: `ArrayAccess`, `HttpKernelInterface`, `mount()`, `extend()`, `before()`, `after()` |
| `Kernel\Application` | Die Fuge — erbt heute von Silex, verliert in `009-002` die Vererbung |
| `Kernel\ControllerProviderInterface` | Weil Silex' Fassung `connect(Silex\Application $app)` vorschreibt und PHP eine Ersetzung des Parametertyps nicht erlaubt |
| `Kernel\ConsoleEvents` | Derselbe Wert `'console.init'` im eigenen Baum |
| `Kernel\Command` | Dieselbe Fuge für die Console-Commands |

Dazu zwei Dinge, die wieder **verschwunden** sind: `redirect()` und `stream()` standen
absichtlich befristet in der Schnittstelle und sind mit ihrem letzten Aufrufer herausgefallen.

### Was bewusst nicht passiert ist

Der Container-Zugriff `$app['schlüssel']` steht unverändert. Er ist zugesichertes API für
Bestandsprojekte — `custom/app.php` registriert seine Dienste genau so —, und der Bridge dafür
entsteht im Schnitt.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (249 tests, 616 assertions)`, 0 übersprungen — 247 vor der Story, plus die zwei des Wächters |
| Wächter, beide Richtungen | eingebaute Verwendung rot, überflüssige Ausnahme rot, sonst grün als bestandener Test |
| Console | `php bin/console.php list` unverändert; `appcms:token:cleanup --dry-run` läuft über den neuen Weg |
| Routing | ungesicherte Vorlagen-Route ohne Token 200, gesicherte 401 / mit Token 200 |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen; Postausgang 0 Byte |
