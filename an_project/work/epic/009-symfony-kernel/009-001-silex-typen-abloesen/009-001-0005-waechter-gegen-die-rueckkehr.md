---
id: 009-001-0005
title: Ein Wächter gegen die Rückkehr der Silex-Typen
status: review
depends_on: [009-001-0002, 009-001-0003, 009-001-0004, 009-001-0006]
---

# Ein Wächter gegen die Rückkehr der Silex-Typen

## Context
Ohne Prüfung ist die Ablösung eine Momentaufnahme. Derselbe Gedanke wie bei
`tests/Unit/AutoloaderUeberschneidungTest.php` aus `006-004-0003`: Eine Bedingung, die niemand
prüft, ist keine Zusicherung — und genau daran ist der alte Zustand jahrelang vorbeigelaufen.

Der Wächter hält fest, wo `Silex\` und `Pimple\` **noch** stehen dürfen: im Bootstrap und in
`Classes/Kernel/`. Das ist keine Ausnahmeliste zum Wachsen, sondern die Liste dessen, was
`009-002` anfasst — sie schrumpft dort auf null.

## Acceptance criteria
- [x] Ein Unit-Test schlägt fehl, sobald `Silex\` oder `Pimple\` ausserhalb der erlaubten
      Stellen auftaucht. Er läuft ohne Datenbank.
- [x] Die erlaubten Stellen stehen als benannte Liste mit Begründung im Test, nicht als Muster.
      Verschwindet eine, meldet der Test auch das — eine Ausnahme, die nicht mehr gebraucht
      wird, ist genauso ein Befund wie eine neue Verwendung (dieselbe Regel wie beim
      Audit- und Deprecation-Gate aus `006-005`).
- [x] Beide Richtungen sind belegt: mit einer eingebauten Verwendung rot, ohne grün — und grün
      als bestandener Test, nicht als übersprungener.

## Verification
`./vendor/bin/phpunit --testsuite unit` grün. Gegenprobe: eine `use Silex\Application;`-Zeile
in eine beliebige Datei ausserhalb der Liste einsetzen, Test läuft rot, Zeile zurücknehmen.

## Ergebnis

`tests/Unit/Kernel/KeineSilexTypenTest.php` prüft beide Richtungen und läuft ohne Datenbank.
Die Unit-Suite wächst von 41 auf **43**, die volle von 247 auf **249**.

### Was in der Liste steht — fünf Stellen, und jede ist eine Aufgabe

| Stelle | Warum sie noch stehen darf |
|---|---|
| `bootstrap.php` | Baut den Kernel auf: vier `register()`-Aufrufe für Service-, Doctrine-, Validator- und Console-Provider |
| `Kernel\Application.php` | Die Fuge zu Silex — hier fällt in `009-002` die Vererbung weg |
| `Kernel\Command.php` | Dieselbe Fuge zu `Knp\Command\Command` |
| `Command\InstallCommand.php` | `bootDoctrine()` registriert Silex' `DoctrineServiceProvider`, weil der Bootstrap bei `is_installed == false` weder DBAL noch ORM registriert |
| `tests/…/RouteAndConsoleManagerTest.php` | Prüft eine echte Eigenschaft von Pimple — ein Service friert beim ersten Auslesen ein, ein `extend()` danach wirft |

**Das ist keine Ausnahmeliste, sondern die Arbeitsliste für `009-002`.** Deshalb prüft der Test
auch die Gegenrichtung: Deckt ein Eintrag nichts mehr ab, gehört er heraus. Eine Ausnahme, die
nichts mehr abdeckt, sieht aus wie eine offene Baustelle und verschleiert den Fortschritt —
dieselbe Regel wie beim Audit- und Deprecation-Gate aus `006-005`.

### Zwei Entscheidungen im Test

**Kommentarzeilen zählen nicht.** Die Ablösung ist an vielen Stellen im Code begründet, und dort
*muss* der alte Name vorkommen — eine Begründung, die den abgelösten Namen nicht nennen darf,
erklärt nichts. Gemeint ist Code.

**Der Wächter nimmt sich selbst aus, aber nicht über `ERLAUBT`.** Er muss die gesuchten Namen
nennen, um nach ihnen zu suchen; beim ersten Lauf hat er sich prompt selbst gemeldet. Die
Ausnahme steht in einer eigenen Konstante, weil er im Gegensatz zu den fünf Einträgen bleibt,
wenn Silex weg ist.

### Nachweis, beide Richtungen gemessen

| Probe | Ergebnis |
|---|---|
| `use Silex\Application as Rueckfall;` in `Classes/Permission.php` | rot, mit Datei und Zeile |
| Erfundener Eintrag in `ERLAUBT` für eine saubere Datei | rot: „nennt keinen der Namensräume mehr" |
| Ohne beides | `OK (2 tests, 2 assertions)` — bestanden, nicht übersprungen |
| Volle Suite | `OK (249 tests, 616 assertions)`, 0 übersprungen |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen; Postausgang 0 Byte |
