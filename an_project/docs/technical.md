<!-- PURPOSE: Architektur, Tech-Stack und technische Konventionen DIESES Projekts. -->

# Technische Konventionen und Ist-Zustand

Der Ziel-Stack steht in `an_project/docs/tech-stack.md`, die Kernel-Entscheidung in
`an_project/docs/architecture.md`. Hier steht, wie der heutige Stand zustande kam — Wissen, das
weder aus dem Code noch aus der Git-Historie hervorgeht.

## `custom/` ist eine Vorlage, kein halbes Projekt

`custom/` enthält absichtlich nur je ein `Example`-Artefakt (`Controller/Core/ExampleController.php`,
`Entity/Core/Example.php`, `Classes/Service/Core/ApiResponseService.php`, `Command/ExampleCommand.php`).
Der Ordner wurde aus einem größeren Kundenprojekt kopiert, ausgedünnt und auf `Example` umgestellt.
Er dient als **Hilfe und Referenz**, was aktuell wie eingesetzt wird.

Konsequenz für alle, die den Baum lesen: `custom/app.php` (1358 Zeilen, 82 `mount()`-Aufrufe)
referenziert Klassen, die hier bewusst nicht liegen. Das ist kein Defekt und kein
unvollständiger Import. Dasselbe gilt für `custom/vendor` — der Inhalt stammt aus dem
Kundenprojekt und ist nicht der Soll-Zustand der Vorlage.

## Warum `vendor/` in Git liegt

Der Root-`vendor/`-Baum ist committet und hat **kein `composer.json`**. Das war kein Versehen,
sondern eine bewusste Notlösung: Um Contentfly ohne größeres Update und ohne Ausfallzeiten
produktiv auf PHP 8.x weiterbetreiben zu können, wurde ein schnelles „Fake"-Update gefahren —
Composer entfernt, der funktionierende Vendor-Stand eingefroren und versioniert.

Der Preis dafür ist die heutige Lage: kein Upgrade-Pfad, kein `composer audit`, keine
ausdrückbaren Version-Constraints. Die Rückführung auf Composer ist Epic `006-000-0000`.

## Zwei Autoloader in einem Prozess

`lib/contentfly/bootstrap.php:8-10` lädt erst `vendor/autoload.php`, dann — falls vorhanden —
`custom/vendor/autoload.php`. Dadurch existieren Pakete doppelt, teils in inkompatiblen Majors:

| Paket | Root | `custom/` | Wirkung |
|---|---|---|---|
| `psr/log` | 1.1.3 | 3.0.2 | Der zuerst registrierte Loader gewinnt, also der alte. `sentry/sentry` 4.22 verlangt `^3` und läuft real gegen die 1.x-Interfaces — Signatur-Risiko. |
| `symfony/polyfill-ctype` | 1.14.0 | 1.37.0 | Durch `function_exists`-Guards unkritisch, aber Ballast. |
| `symfony/polyfill-mbstring` | 1.14.0 | 1.38.2 | dito |

Dazu treffen zwei Symfony-Generationen aufeinander: `symfony/http-foundation` 3.4 im Root gegen
`symfony/options-resolver` 7.4 in `custom/` (via Sentry).

## Was den Alt-Baum an PHP 8.5 hindert

| Paket | Constraint | Folge |
|---|---|---|
| `ellumilel/php-excel-writer` | `php: ^5.4\|^7.0` | Unter PHP 8.5 **nicht installierbar**. Genutzt in `lib/contentfly/Controller/ExportController.php:9`. |
| `silex/silex` 2.2.2 | `symfony/*: ~2.8\|^3.0` | Deckelt Symfony auf 3.4 (EOL Nov 2020, nie für PHP 8 freigegeben). Der `php`-Constraint ist nach oben offen — Composer meldet nichts. |
| `dflydev/doctrine-orm-service-provider` | `doctrine/orm: ~2.3` | Blockiert den Weg auf ORM 3. |
| `doctrine/orm` | `dev-bugfix-many2many` | Dev-Branch-Pin ohne Release — in keinem Upgrade-Pfad ausdrückbar. |

Dev-Werkzeuge liegen heute im ausgelieferten Baum: `phpstan/phpstan` 1.10.58 und `rector/rector`
1.0.1 im Root, `phpunit` 10.5 und `mockery` in `custom/vendor`.
