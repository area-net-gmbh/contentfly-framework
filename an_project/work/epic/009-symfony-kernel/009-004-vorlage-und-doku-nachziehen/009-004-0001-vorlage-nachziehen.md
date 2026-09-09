---
id: 009-004-0001
title: Die Vorlage custom/ auf den neuen Kernel nachziehen
status: done
depends_on: [009-004-0004]
---

# Die Vorlage custom/ auf den neuen Kernel nachziehen

## Context
`custom/` ist das, was ein Bestandsprojekt als Referenz bekommt. Beschreibt es den Silex-Stand,
lehrt es das Falsche — und Epic `007` hat keine Grundlage.

`custom/app.php` zeigt vier Muster: Service, Route, Middleware, Console-Command. **Alle vier
funktionieren unverändert** — das war der Zweck der Schnittstelle aus `009-001`. Was sich
geändert hat, sind die Erklärungen daneben: Der Kommentar spricht von Pimple-Factories, vom
`RouteManager` als Silex-Aufsatz und von Hooks, die Silex-Prioritäten nehmen.

**Die offene Inkonsistenz ist zu entscheiden.** `technical.md` hält seit `012` fest:

> `custom/Command/ExampleCommand.php` erbt von `Symfony\…\Console\Command` und erwartet `$app`
> im Konstruktor. Der `ConsoleManager` des Frameworks nimmt aber ausschliesslich
> `Areanet\PIM\Classes\Command\CustomCommand`. Das Beispiel ist deshalb in `custom/app.php`
> bewusst **nicht** registriert — es zeigt einen Weg, den das Framework so nicht anbietet.
> Beim Aufräumen der Vorlage zu entscheiden: Beispiel auf `CustomCommand` umstellen, oder den
> `ConsoleManager` für gewöhnliche Symfony-Commands öffnen.

Der Kernel-Wechsel ist der Zeitpunkt: `Kernel\Command` erbt jetzt direkt von Symfonys `Command`,
also stehen die beiden Wege näher beieinander als vorher.

## Acceptance criteria
- [x] Die Entscheidung zur `ExampleCommand`-Inkonsistenz ist getroffen und begründet, und die
      Notiz in `technical.md` ist entsprechend aufgelöst statt stehengelassen.
- [x] Das Beispiel ist in `custom/app.php` registriert oder es steht dort, warum nicht — kein
      dritter Zustand, in dem eine Datei herumliegt, die niemand benutzt und niemand erklärt.
- [x] Die Kommentare in `custom/app.php` beschreiben den neuen Kernel: Container, Routing,
      Hooks, Console. Was sich für ein Projekt **nicht** ändert, steht ausdrücklich da — das
      ist die wichtigere Hälfte.
- [x] `custom/config.php` ist geprüft: Alle Schalter, die es beschreibt, gibt es noch, und die
      Erklärungen stimmen (`WEB_ROOT` hat sich mit `000-000-0006` geändert).
- [x] Die Suite bleibt grün; `VorlageApiTest` prüft die Vorlage über HTTP.

## Verification
`./vendor/bin/phpunit --filter VorlageApiTest`, dann die volle Suite. Dazu
`php bin/console.php list` — steht der Beispiel-Command drin, wenn er registriert wurde.

## Ergebnis

**Entschieden: Das Beispiel erbt von `CustomCommand` und ist registriert.** Es heisst dadurch
`custom:example:command:run` — der Präfix kommt vom `ConsoleManager`, nicht vom Command.

Der andere Weg wäre gewesen, den `ConsoleManager` für jedes Symfony-`Command` zu öffnen. Dagegen
spricht der Präfix selbst: Er ist die Zusicherung, dass ein Projekt-Command nie einen des
Frameworks überschreibt. Ein offener Manager macht daraus ein Angebot, und `appcms:install`
wäre überschreibbar. Die Notiz in `technical.md` ist damit aufgelöst, nicht gestrichen — der
Abschnitt heisst jetzt *Aufgelöst* und trägt die Begründung.

### Der Fund, der aus der Registrierung fiel

Die Registrierung liess `php bin/console.php list` sofort mit
`RuntimeException: Der Dienst "dispatcher" ist bereits ausgelesen` sterben. Ursache war der
Kernel, nicht die Vorlage: `before()` las den Dispatcher aus und fror ihn ein, jede spätere
Console-Anmeldung scheiterte. Behoben in `009-004-0004`, das deshalb vor diesem Task liegt.

**Die Vorlage ist ihren eigenen dokumentierten Weg nie gegangen** — Middleware und Command
standen beide in `custom/app.php` beschrieben, aber der Command war nicht registriert. Genau in
dieser Lücke sass der Defekt.

### Zwei Zusicherungen umgedreht

`VorlageApiTest` hielt den alten Zustand fest. Beide Änderungen sind Verhaltenswechsel und als
solche begründet:

| Vorher | Nachher |
|---|---|
| `testDerBeispielCommandIstAbsichtlichNichtRegistriert` | `testDerBeispielCommandIstRegistriertUndTraegtDenCustomPraefix` |
| Hook-Kommentar nennt „Silex-Priorität" | Kommentar nennt „die Reihenfolge der Registrierung die Ausführungsreihenfolge" |

### `custom/config.php`

Jeder beschriebene Schalter existiert noch — `is_installed`, `APP_DEBUG`,
`APP_ENABLE_SCHEMA_CACHE`, `APP_TIMEZONE`, `SECURITY_CIPHER_KEY`, `APP_CS_POLICY`,
`DB_GUID_STRATEGY` je gegen den lesenden Code geprüft.

**Eine Lücke in die andere Richtung:** `WEB_ROOT` fehlte. `bootstrap-web.php` verweist seit
`000-000-0006` darauf, ein Projekt setze den Wert „in `custom/config.php`" — dort stand er nie.
Wer die Anwendung in ein Unterverzeichnis hängt, hätte den Schalter nur im Framework-Code
gefunden. Jetzt steht er auskommentiert in der Vorlage, mit dem Grund daneben.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `php bin/console.php list` | zeigt `custom:example:command:run` |
| Der Command ausgeführt | gibt seine Meldung aus, Exit 0 |
| `VorlageApiTest` | `OK (13 tests, 55 assertions)` |
| Volle Suite | `OK (267 tests, 640 assertions)` |
