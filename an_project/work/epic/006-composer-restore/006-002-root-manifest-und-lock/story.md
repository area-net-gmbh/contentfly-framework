---
id: 006-002-0000
title: Root-Manifest und Lock für den Ist-Stack
status: in-progress
depends_on: [006-001-0000]
---

# Root-Manifest und Lock für den Ist-Stack

## Goal
Das Repo bekommt wieder ein `composer.json` — für den **Ist-Stack**. Damit ist Composer ab hier
die Quelle der Wahrheit für Abhängigkeiten, der committete `vendor/`-Baum verliert seine
Berechtigung (`006-003`), und `composer audit` wird möglich (`006-005`).

> **Diese Story wurde am 2026-09-08 umgeschrieben.** Die ursprüngliche Fassung beschrieb den
> **Ziel**-Stack (PHP 8.5, Symfony 7.4, kein Silex) und nahm dafür in Kauf, dass der Baum bis
> Epic `009` nicht mehr bootet. Das ist verworfen: Epic `008` hat die Testsuite als
> **Abnahmegrundlage für `009`** festgeschrieben (`an_project/docs/technical.md`) — eine Suite,
> die über mehrere Epics rot steht, kann diese Rolle nicht erfüllen. Begründung im Epic-Text.

## Umfang

### Was das Manifest beschreibt
Den heutigen Stack, PHP-8.3-tauglich gemacht. **Silex 2 und die Symfony-Komponenten bleiben** —
sie fallen mit Epic `009`, nicht hier.

`006-001-0003` hat den Auflösungslauf bereits durchgeführt; die Zahlen stehen dort. Zwei
Varianten lösen auf, **Variante C ist die Empfehlung**:

| Paket | heute (eingefroren) | Ziel dieser Story |
|---|---|---|
| `silex/silex` | v2.2.2 | v2.3.0 |
| `symfony/*` (Silex-Gruppe) | 3.4 | **4.4** |
| `doctrine/orm` | `dev-bugfix-many2many` | **2.20.x** (Release) |
| `doctrine/dbal` | v2.6.3 | **2.13.x** — bewusst *nicht* 3.x |
| `doctrine/annotations` | v1.8.0 | 1.14.x |
| `ramsey/uuid` | 3.8.0 | **^4** |

### Symfony 4.4 ist erzwungen, nicht gewählt
Der wichtigste Fund aus `006-001-0003`, weil er den Umfang bestimmt:

1. PHP 8.3 → `doctrine/orm` muss auf ein **Release** (der Dev-Pin cappt auf `php ^7.1`).
2. Jedes ORM-Release ab 2.14 → verlangt `symfony/console ^4.2` oder höher.
3. **Also ist Symfony 3.4 ausgeschlossen.**
4. Nach oben deckelt `knplabs/console-service-provider` (v2.2.0) auf `symfony/console ^4.0`.

Zwischen Doctrine als Untergrenze und Silex/knplabs als Obergrenze bleibt **genau 4.4**. Es sind
damit nicht zwei Pakete anzuheben, sondern der halbe Baum.

### `config.platform.php` auf 8.3
Nicht 8.5. Die Suite ist heute auf 8.3 grün, und die Pipeline aus `008-005-0001` fährt sie
gegen 8.3 (pflicht) und 8.4 (`allow_failure`). Ein Manifest, das gegen 8.5 auflöst, während
niemand gegen 8.5 testet, verspräche etwas Ungeprüftes.

Der Sprung auf 8.5 gehört zu `009`, gemeinsam mit Symfony 7.4 — dort ist es ein
Constraint-Bump im selben Manifest.

### Was entfällt
Aus der Zuordnung in `006-001-0004`, die hier eingelöst wird:

- `ellumilel/php-excel-writer`, `twig/twig`, `scssphp/scssphp` — Konsumenten mit Epic `012`
  bzw. `006-001-0001` gefallen.
- `firebase/php-jwt`, `sentry/sentry`, `onelogin/php-saml`, `robrichards/xmlseclibs`,
  `stripe/stripe-php` — **werden nirgends benutzt**; Reste aus der Kundenanwendung. Epic `013`
  nimmt JWT wieder auf, wenn es tatsächlich eingebaut wird.
- `mockery/mockery` — steht in `custom/composer.json`, wird in keinem Test benutzt.
- `paragonie/random_compat`, `symfony/polyfill-php56`, `-php70`, `-util` — Polyfills für PHP 5
  und 7.

### Was ins `require-dev` wandert
`phpstan/phpstan` und `rector/rector` liegen heute im **Produktions**baum, `phpunit/phpunit` in
`custom/`. Alle drei gehören ins Root-`require-dev`.

Für `.gitlab-ci.yml` heisst das: Der PHPUnit-Pfad ist von `./custom/vendor/bin/phpunit` auf
`./vendor/bin/phpunit` umzustellen. Er steht dort als Variable an **einer** Stelle — genau
dafür (`008-005-0001`).

### Lock und Autoload
- `composer.lock` wird erzeugt und **committet** — Voraussetzung für reproduzierbare CI-Läufe
  und für `composer audit --locked` (`006-005`).
- Der `autoload`-Abschnitt bildet die heutigen Namensräume ab (`Areanet\PIM\` → `lib/contentfly`,
  `Custom\` → `custom`). Die Verdrahtung des zweiten Baums ist `006-004`.

## Das Risiko, das diese Story trägt
`doctrine/orm` steht heute auf einem **eigenen Fork**: `area-net-gmbh/doctrine2`, Branch
`bugfix-many2many`, Stand **2018-08-07**. Kein offizieller Doctrine-Branch, sondern ein
Firmen-Fork mit einem ManyToMany-Fix.

Ob dieser Fix in 2.20 aufgegangen ist, lässt sich aus dem Baum nicht klären. Der praktische
Nachweis wäre die Suite — **und ausgerechnet der ManyToMany-Pfad ist eine der in
`an_project/docs/technical.md` dokumentierten Lücken**: Die Schreibprüfung des `MultijoinType`
ist mit der Vorlage nicht auslösbar, weil der einzige Multijoin (`PIM\File.tags`) kein
`acceptFrom` hat.

Vor dem Doctrine-Wechsel ist deshalb zu entscheiden: gezielter ManyToMany-Test, oder das Risiko
ausdrücklich annehmen. **Nicht stillschweigend übergehen.**

## Offene Fragen
- **Bezugsweg des Frameworks.** Ob `lib/contentfly` künftig als Composer-Paket
  `areanet/contentfly` bezogen wird statt als kopierter Baum, entscheidet Epic `007`. Dieses
  Manifest greift dem **nicht vor** — es darf aber auch keine Paketgrenze verbauen. Konkret: Das
  Autoload-Layout wird so gewählt, dass `lib/contentfly` später ohne Umbau als eigenes Paket
  herausgelöst werden kann.
- **Fünf abandoned Pakete** meldet die Auflösung: `silex/silex`, `symfony/debug`,
  `doctrine/annotations`, `doctrine/cache`, `knplabs/console-service-provider`. Für `006-005`
  ist zu entscheiden, ob „abandoned" das Audit-Gate rot färbt — sonst steht es am ersten Tag
  rot, ohne dass eine Sicherheitslücke vorliegt. Hier nur zu vermerken.

## Fertig, wenn
- `composer.json` und `composer.lock` liegen im Repo-Root und beschreiben den Ist-Stack.
- `config.platform.php` steht auf `8.3`.
- `doctrine/orm` zeigt auf eine releaste Version; die Auflösung des Dev-Pins ist begründet
  festgehalten, samt der Entscheidung über den ManyToMany-Test.
- Die Entfaller aus `006-001-0004` stehen in keinem `require`.
- `phpstan`, `rector` und `phpunit` stehen im `require-dev`.
- `composer install` läuft gegen PHP 8.3 ohne `--ignore-platform-reqs` durch.
- **Die Suite bleibt grün** — 238 Tests / 575 Assertions. Sie ist das Abnahmekriterium auch
  für diese Story.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 006-002-0001 — Den Doctrine-Wechsel absichern
- [ ] 006-002-0002 — composer.json für den Ist-Stack schreiben
- [ ] 006-002-0003 — Lock erzeugen und gegen die Suite abnehmen
- [ ] 006-002-0004 — Pipeline und Runbook nachziehen
- [ ] 006-002-0005 — dflydev-Service-Provider durch eigenen Aufbau ersetzen
