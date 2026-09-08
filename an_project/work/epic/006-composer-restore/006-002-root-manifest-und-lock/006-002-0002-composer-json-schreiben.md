---
id: 006-002-0002
title: composer.json für den Ist-Stack schreiben
status: review
depends_on: [006-002-0001]
---

# composer.json für den Ist-Stack schreiben

## Context
Das Repo hat seit dem Notfall-Update kein Root-Manifest. Dieser Task schreibt es — und löst
damit die Zuordnung aus `006-001-0004` ein.

**Nur das Manifest.** Der Lock entsteht in `006-002-0003`, `vendor/` verschwindet in `006-003`.
Die Trennung ist Absicht: Ein Manifest zu schreiben ist eine Entscheidung, einen Lock zu
erzeugen ist ein Experiment mit ungewissem Ausgang. Scheitert das Experiment, war die
Entscheidung trotzdem richtig und muss nicht neu gedacht werden.

## Umfang

### Die Vorlage steht schon
`006-001-0003` hat das Manifest bereits durchgerechnet (Variante C, Exit 0). Es ist zu
übernehmen und zu vervollständigen, nicht neu zu erfinden:

```json
{
    "config": { "platform": { "php": "8.3.0" } },
    "require": {
        "php": "^8.3",
        "silex/silex": "^2.2",
        "doctrine/orm": "^2.14",
        "doctrine/dbal": "^2.13",
        "doctrine/annotations": "^1.14",
        "dflydev/doctrine-orm-service-provider": "^2.0",
        "knplabs/console-service-provider": "^2.0",
        "ramsey/uuid": "^4.0",
        "phpmailer/phpmailer": "^6.10",
        "symfony/console": "^4.4",
        "symfony/validator": "^4.4",
        "symfony/translation": "^4.4"
    }
}
```

Zu ergänzen sind `vlucas/phpdotenv` (aus der Zuordnung ins Root gewandert), der
`require-dev`-Block und `autoload`.

### `require-dev`
`phpstan/phpstan`, `rector/rector` (liegen heute im **Produktions**baum) und `phpunit/phpunit`
(liegt heute in `custom/`). **Nicht** `mockery/mockery` — es wird in keinem Test benutzt
(`006-001-0004`).

### `autoload`
Bildet die heutigen Namensräume ab:
- `Areanet\PIM\` → `lib/contentfly`
- `Custom\` → `custom`

**Die Paketgrenze nicht verbauen.** Epic `007` entscheidet, ob `lib/contentfly` künftig als
Composer-Paket `areanet/contentfly` bezogen wird. Dieses Manifest greift dem nicht vor, muss
aber so gebaut sein, dass sich `lib/contentfly` später ohne Umbau herauslösen lässt — also
keine Verschränkung der beiden Präfixe über gemeinsame Verzeichnisse.

`tests/` bleibt aussen vor: `Tests\` ist bewusst nicht autoloadbar, `tests/bootstrap.php` lädt
die Basisklasse per `require_once` (`008-001-0001`). Ob das mit einem Root-Manifest sauberer
geht, ist eine Frage für später — hier wird der Ist-Zustand nicht angetastet.

### Was nicht hineingehört
Die 22 Entfaller aus `006-001-0004`, namentlich: `ellumilel/php-excel-writer`, `twig/twig`,
`scssphp/scssphp`, `firebase/php-jwt`, `sentry/sentry`, `onelogin/php-saml`,
`robrichards/xmlseclibs`, `stripe/stripe-php`, `mockery/mockery`, `paragonie/random_compat`
und die drei alten `symfony/polyfill-*`.

Transitive Abhängigkeiten stehen **nicht** im `require` — das ist die Aufgabe des Locks.

## Abgrenzung
Kein `composer install`, kein Lock, kein Anfassen von `vendor/`. Auch keine Änderung an
`custom/composer.json` — das ist `006-004`.

## Acceptance criteria
- [x] `composer.json` liegt im Repo-Root, ist gültiges JSON und beschreibt den Ist-Stack.
- [x] `config.platform.php` steht auf `8.3.0`.
- [x] `require` enthält kein Paket aus der Entfaller-Liste.
- [x] `require-dev` enthält `phpstan`, `rector` und `phpunit` — und **nicht** `mockery`.
- [x] `autoload` bildet beide Namensräume ab und verbaut die spätere Paketgrenze nicht.
- [x] `composer validate` läuft ohne Fehler durch.
- [x] Ein Kommentarblock (`description` oder ein `_hinweis`) verweist auf `006-001-0003` —
      wer die Constraints später ändert, muss wissen, dass Symfony 4.4 erzwungen ist.

## Verification
`composer validate --no-check-all` und `composer update --dry-run` — Letzteres muss auflösen,
ohne dass etwas installiert wird. Das Protokoll gehört ins Ergebnis.

Die Suite läuft in diesem Task noch gegen den **alten** `vendor/`-Baum und muss unverändert
grün bleiben: Ein blosses `composer.json` im Root darf nichts an der Anwendung ändern. Bleibt
sie nicht grün, hat das Manifest eine Nebenwirkung, die niemand beabsichtigt hat.

## Ergebnis
`composer.json` liegt im Repo-Root. **12 Pakete im `require`** plus `php`, **3 im
`require-dev`**. `composer validate` läuft durch, `composer update --dry-run` löst mit Exit 0
auf.

### Was der Auflösungslauf gegen den heutigen Baum ergibt
| | Anzahl |
|---|---|
| Upgrading | 29 |
| Installing | 47 |
| Removing | **8** |
| Downgrading | 0 |

Die acht Entfernungen decken sich mit der Zuordnung aus `006-001-0004`:
`twig/twig`, `ellumilel/php-excel-writer`, `paragonie/random_compat`, die drei alten
`symfony/polyfill-*` — dazu `symfony/contracts` und `doctrine/reflection`, die transitiv
wegfallen.

Und die Zeile, um die es geht:

```
doctrine/orm (dev-bugfix-many2many 57e64c2 => 2.20.13)
```

### Der Hinweis, der die Constraints schützt
Composer erlaubt keine Kommentare in JSON, also steht die Begründung unter `extra.hinweis` —
ein vorgesehener Ort für Beliebiges, den `composer validate` nicht beanstandet.

Sie erklärt die **Zwangskette**: PHP 8.3 → ORM-Release → `symfony/console ^4.2` → gedeckelt
durch `knplabs` auf `^4.0`. Wer eine der drei Zeilen ändert, muss die Kette neu rechnen. Ohne
diesen Hinweis liest jemand `symfony/console: ^4.4` und hält es für Bequemlichkeit statt für
das einzige, was übrig bleibt.

Ebenso begründet: warum `doctrine/dbal` auf `^2.13` und nicht `^3` steht, und warum
`platform.php` auf `8.3` und nicht `8.5`.

### Autoload
```json
"psr-4": {
    "Areanet\PIM\": "lib/contentfly/",
    "Custom\": "custom/"
}
```

Beides steht heute schon so im generierten `vendor/composer/autoload_psr4.php` (Zeilen 44–45)
— das Manifest schreibt nur auf, was ohnehin gilt.

**Die Paketgrenze bleibt offen:** `lib/contentfly/` und `custom/` sind getrennte Verzeichnisse
ohne Überschneidung. Epic `007` kann `lib/contentfly` ohne Umbau als eigenes Paket
`areanet/contentfly` herauslösen.

`tests/` bleibt aussen vor — `Tests\` ist bewusst nicht autoloadbar, `tests/bootstrap.php`
lädt die Basisklasse per `require_once` (`008-001-0001`). Der Ist-Zustand wird hier nicht
angetastet.

### Die Verifikation: keine Nebenwirkung
Das war der Punkt, den dieser Task belegen musste — ein blosses `composer.json` im Root darf
an der laufenden Anwendung nichts ändern:

- `php bin/console.php list` → Exit 0
- `GET /api/config` → HTTP 200
- Vollständige Suite gegen den **alten** `vendor/`-Baum → **247 Tests / 603 Assertions grün**
- `git status vendor/ custom/vendor/` → leer, beide Bäume unberührt

Der Lock und der tatsächliche Wechsel sind `006-002-0003`.
