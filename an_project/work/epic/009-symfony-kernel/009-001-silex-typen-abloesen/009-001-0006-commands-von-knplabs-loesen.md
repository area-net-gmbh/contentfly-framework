---
id: 009-001-0006
title: Die Console-Commands von knplabs lösen
status: review
depends_on: [009-001-0001]
---

# Die Console-Commands von knplabs lösen

## Context
Aufgefallen beim Nachzählen in `009-001-0003`: Die Oberfläche, die `009-001-0001` gemessen hat,
ist gegen `Silex\` und `Pimple\` gemessen worden — **nicht gegen `Knp\`**. Dabei muss
`knplabs/console-service-provider` genauso weg: Es deckelt `symfony/console` auf `^4` und ist
damit unter Symfony 7.4 nicht mitzunehmen.

Fünf Dateien hängen daran, und zwar über **Vererbung**, nicht über eine Typangabe:

| Datei | Was sie erbt |
|---|---|
| `Classes\Command\CustomCommand` | `Knp\Command\Command` — die Basisklasse, die Projekte für eigene Commands benutzen |
| `Command\InstallCommand` | dieselbe, plus `getSilexApplication()` |
| `Command\SetupCommand` | dieselbe, plus `getSilexApplication()` |
| `Command\TokenCleanupCommand` | dieselbe, plus `getSilexApplication()` |

`Knp\Command\Command` ist zwanzig Zeilen: Es erweitert `Symfony\…\Console\Command\Command` um
`getSilexApplication()` und `getProjectDirectory()`, beide reine Weiterleitungen an die
Console-Anwendung.

## Acceptance criteria
- [x] Es gibt `Areanet\PIM\Classes\Kernel\Command`, das heute von `Knp\Command\Command` erbt —
      dieselbe Fuge wie `Kernel\Application` bei Silex. Der Klassenkommentar sagt, was `009-002`
      damit tut.
- [x] `CustomCommand` und die drei Framework-Commands erben von der eigenen Klasse; keine von
      ihnen nennt `Knp\` noch.
- [x] Der Zugriff auf die Anwendung läuft über einen eigenen Namen, nicht über
      `getSilexApplication()` — der Name beschreibt beim neuen Kernel sonst das Falsche.
- [x] `custom/app.php` beschreibt weiterhin richtig, wovon ein Projekt-Command erben muss.
- [x] Kein Verhalten ändert sich: `php bin/console.php list` zeigt dieselben Commands,
      `appcms:install` und `appcms:token:cleanup` laufen.

## Verification
`./vendor/bin/phpunit` grün, `php bin/console.php list`, und `appcms:token:cleanup --dry-run`
gegen die Testinstanz — der Command, der die Anwendung tatsächlich benutzt.

## Ergebnis

`Areanet\PIM\Classes\Kernel\Command` steht als Fuge — dieselbe Konstruktion wie
`Kernel\Application` bei Silex, aus demselben Grund. Statt fünf Dateien, die
`knplabs/console-service-provider` nennen, nennt es eine. Im ganzen Baum bleiben `Knp\` damit
noch zwei Stellen: diese Fuge und `bootstrap.php`, wo der Provider registriert wird.

### `anwendung()` statt `getSilexApplication()`

Der alte Name beschreibt beim neuen Kernel das Falsche, und ein Name, der lügt, ist schlechter
als einer, den man einmal ändern muss. Drei Aufrufstellen — `InstallCommand`, `SetupCommand`,
`TokenCleanupCommand` — sind umbenannt.

**Die geerbte Methode bleibt trotzdem erreichbar.** Ein Bestandsprojekt, dessen Command sie
ruft, bricht nicht; nur der eigene Code benutzt sie nicht mehr. Mit `009-002` fällt sie
zusammen mit dem Paket weg, und dann ist es ein benannter Bruch im Migrationsleitfaden statt
eines stillen.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (247 tests, 614 assertions)`, 0 übersprungen |
| `php bin/console.php list` | die drei `appcms:`-Commands unverändert |
| `appcms:token:cleanup --dry-run` | `0 von 166 Anmeldetoken abgelaufen` — der Command, der die Anwendung tatsächlich benutzt, läuft über den neuen Weg |
