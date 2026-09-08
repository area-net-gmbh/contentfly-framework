<!-- PURPOSE: Inventar beider Vendor-Bäume. ERZEUGT von tools/dependency-inventory.php — nicht von Hand pflegen. -->

# Abhängigkeiten-Inventar

**Erzeugt am 2026-09-08 mit `php tools/dependency-inventory.php`.** Nicht von Hand
pflegen — neu erzeugen. Erhoben mit `006-001-0002`; die **Zuordnung** (Root · `custom/` ·
`require-dev` · entfällt) kommt mit `006-001-0004` dazu.

Die Spalte *benutzt?* ist eine `grep`-Näherung über `lib/`, `custom/` und `bin/` — sie
beantwortet die Frage „lohnt genaueres Hinsehen?", nicht „wird gebraucht?".

**Sie erzeugt Fehlalarme.** `twig/twig` etwa steht auf *ja*, weil das Wort „Twig" in
einem Kommentar von `Command/InstallCommand.php` vorkommt — benutzt wird es nicht. Ein
*ja* heisst: nachsehen. Ein *—* ist die belastbarere Aussage von beiden.

**89 Pakete** insgesamt.

## Root — 41 Pakete

| Paket | Version | `php`-Constraint | Herkunft | benutzt? |
|---|---|---|---|---|
| `dflydev/doctrine-orm-service-provider` | v2.0.1 | `>=5.3.3` | installed.json | ja |
| `doctrine/annotations` | v1.8.0 | `^7.1` | installed.json | ja |
| `doctrine/cache` | 1.10.0 | `~7.1` | installed.json | ja |
| `doctrine/collections` | 1.6.4 | `^7.1.3` | installed.json | ja |
| `doctrine/common` | 2.12.0 | `^7.1` | installed.json | ja |
| `doctrine/dbal` | v2.6.3 | `^7.1` | installed.json | ja |
| `doctrine/event-manager` | 1.1.0 | `^7.1` | installed.json | ja |
| `doctrine/inflector` | 1.3.1 | `^7.1` | installed.json | ja |
| `doctrine/instantiator` | 1.3.0 | `^7.1` | installed.json | ja |
| `doctrine/lexer` | 1.0.2 | `>=5.3.2` | installed.json | ja |
| `doctrine/orm` | dev-bugfix-many2many | `^7.1` | installed.json | ja |
| `doctrine/persistence` | 1.3.7 | `^7.1` | installed.json | ja |
| `doctrine/reflection` | 1.2.0 | `^7.1` | installed.json | ja |
| `ellumilel/php-excel-writer` | v0.1.6 | `^5.4\|^7.0` | installed.json | — |
| `knplabs/console-service-provider` | v2.2.0 | `>=5.5.9` | installed.json | ja |
| `paragonie/random_compat` | v9.99.99 | `^7` | installed.json | — |
| `phpmailer/phpmailer` | (unbekannt) | `>=5.5.0` | **von Hand** | ja |
| `phpstan/phpstan` | 1.10.58 | `^7.2\|^8.0` | installed.json | — |
| `pimple/pimple` | v3.2.3 | `>=5.3.0` | installed.json | — |
| `psr/cache` | 1.0.1 | `>=5.3.0` | installed.json | ja |
| `psr/container` | 1.0.0 | `>=5.3.0` | installed.json | ja |
| `psr/log` | 1.1.3 | `>=5.3.0` | installed.json | — |
| `ramsey/uuid` | 3.8.0 | `^5.4 \|\| ^7.0` | installed.json | ja |
| `rector/rector` | 1.0.1 | `^7.2\|^8.0` | installed.json | ja |
| `scssphp/scssphp` | (unbekannt) | `>=5.6.0` | **von Hand** | — |
| `silex/silex` | v2.2.2 | `>=5.5.9` | installed.json | ja |
| `symfony/console` | v4.2.12 | `^7.1.3` | installed.json | ja |
| `symfony/contracts` | v1.1.8 | `^7.1.3` | installed.json | ja |
| `symfony/debug` | v4.4.5 | `^7.1.3` | installed.json | ja |
| `symfony/event-dispatcher` | v3.4.38 | `^5.5.9\|>=7.0.8` | installed.json | ja |
| `symfony/http-foundation` | v3.4.38 | `^5.5.9\|>=7.0.8` | installed.json | ja |
| `symfony/http-kernel` | v3.4.38 | `^5.5.9\|>=7.0.8` | installed.json | ja |
| `symfony/polyfill-ctype` | v1.14.0 | `>=5.3.3` | installed.json | ja |
| `symfony/polyfill-mbstring` | v1.14.0 | `>=5.3.3` | installed.json | ja |
| `symfony/polyfill-php56` | v1.14.0 | `>=5.3.3` | installed.json | ja |
| `symfony/polyfill-php70` | v1.14.0 | `>=5.3.3` | installed.json | ja |
| `symfony/polyfill-util` | v1.14.0 | `>=5.3.3` | installed.json | ja |
| `symfony/routing` | v3.4.38 | `^5.5.9\|>=7.0.8` | installed.json | ja |
| `symfony/translation` | v4.3.11 | `^7.1.3` | installed.json | ja |
| `symfony/validator` | v4.0.4 | `^7.1.3` | installed.json | ja |
| `twig/twig` | v2.4.4 | `^7.0` | installed.json | ja |

## custom/ — 48 Pakete

| Paket | Version | `php`-Constraint | Herkunft | benutzt? |
|---|---|---|---|---|
| `firebase/php-jwt` | v7.1.0 | `^8.0` | installed.json | — |
| `graham-campbell/result-type` | v1.1.4 | `^7.2.5 \|\| ^8.0` | installed.json | — |
| `guzzlehttp/psr7` | 2.12.5 | `^7.2.5 \|\| ^8.0` | installed.json | — |
| `hamcrest/hamcrest-php` | v2.1.1 | `^7.4\|^8.0` | installed.json | — |
| `jean85/pretty-package-versions` | 2.1.1 | `^7.4\|^8.0` | installed.json | — |
| `mockery/mockery` | 1.6.12 | `>=7.3` | installed.json | — |
| `myclabs/deep-copy` | 1.13.4 | `^7.1 \|\| ^8.0` | installed.json | — |
| `nikic/php-parser` | v5.7.0 | `>=7.4` | installed.json | — |
| `onelogin/php-saml` | 4.3.2 | `>=7.3` | installed.json | — |
| `phar-io/manifest` | 2.0.4 | `^7.2 \|\| ^8.0` | installed.json | — |
| `phar-io/version` | 3.2.1 | `^7.2 \|\| ^8.0` | installed.json | ja |
| `phpmailer/phpmailer` | v6.10.0 | `>=5.5.0` | installed.json | ja |
| `phpoption/phpoption` | 1.9.5 | `^7.2.5 \|\| ^8.0` | installed.json | — |
| `phpunit/php-code-coverage` | 10.1.16 | `>=8.1` | installed.json | — |
| `phpunit/php-file-iterator` | 4.1.0 | `>=8.1` | installed.json | — |
| `phpunit/php-invoker` | 4.0.0 | `>=8.1` | installed.json | — |
| `phpunit/php-text-template` | 3.0.1 | `>=8.1` | installed.json | — |
| `phpunit/php-timer` | 6.0.0 | `>=8.1` | installed.json | — |
| `phpunit/phpunit` | 10.5.63 | `>=8.1` | installed.json | — |
| `psr/http-factory` | 1.1.0 | `>=7.1` | installed.json | — |
| `psr/http-message` | 2.0 | `^7.2 \|\| ^8.0` | installed.json | — |
| `psr/log` | 3.0.2 | `>=8.0.0` | installed.json | — |
| `ralouphie/getallheaders` | 3.0.3 | `>=5.6` | installed.json | — |
| `robrichards/xmlseclibs` | 3.1.5 | `>= 5.4` | installed.json | — |
| `sebastian/cli-parser` | 2.0.1 | `>=8.1` | installed.json | — |
| `sebastian/code-unit` | 2.0.0 | `>=8.1` | installed.json | — |
| `sebastian/code-unit-reverse-lookup` | 3.0.0 | `>=8.1` | installed.json | — |
| `sebastian/comparator` | 5.0.5 | `>=8.1` | installed.json | — |
| `sebastian/complexity` | 3.2.0 | `>=8.1` | installed.json | — |
| `sebastian/diff` | 5.1.1 | `>=8.1` | installed.json | ja |
| `sebastian/environment` | 6.1.0 | `>=8.1` | installed.json | — |
| `sebastian/exporter` | 5.1.4 | `>=8.1` | installed.json | — |
| `sebastian/global-state` | 6.0.2 | `>=8.1` | installed.json | — |
| `sebastian/lines-of-code` | 2.0.2 | `>=8.1` | installed.json | — |
| `sebastian/object-enumerator` | 5.0.0 | `>=8.1` | installed.json | — |
| `sebastian/object-reflector` | 3.0.0 | `>=8.1` | installed.json | — |
| `sebastian/recursion-context` | 5.0.1 | `>=8.1` | installed.json | — |
| `sebastian/type` | 4.0.0 | `>=8.1` | installed.json | ja |
| `sebastian/version` | 4.0.1 | `>=8.1` | installed.json | ja |
| `sentry/sentry` | 4.22.0 | `^7.2\|^8.0` | installed.json | — |
| `stripe/stripe-php` | v18.2.0 | `>=5.6.0` | installed.json | ja |
| `symfony/deprecation-contracts` | v3.6.0 | `>=8.1` | installed.json | ja |
| `symfony/options-resolver` | v7.4.0 | `>=8.2` | installed.json | ja |
| `symfony/polyfill-ctype` | v1.37.0 | `>=7.2` | installed.json | ja |
| `symfony/polyfill-mbstring` | v1.38.2 | `>=7.2` | installed.json | ja |
| `symfony/polyfill-php80` | v1.37.0 | `>=7.2` | installed.json | ja |
| `theseer/tokenizer` | 1.3.1 | `^7.2 \|\| ^8.0` | installed.json | — |
| `vlucas/phpdotenv` | v5.6.4 | `^7.2.5 \|\| ^8.0` | installed.json | — |

## Geisterpakete

Verzeichnisse, die in **keiner** `installed.json` stehen. Von Hand hineinkopiert;
Composer weiss von ihnen nichts, der Autoloader schon.

- `phpmailer/phpmailer` (in Root)
- `scssphp/scssphp` (in Root)

## Dubletten — dasselbe Paket in beiden Bäumen

| Paket | Root | `custom/` | gleicher Major? |
|---|---|---|---|
| `phpmailer/phpmailer` | (unbekannt) | v6.10.0 | **nein** |
| `psr/log` | 1.1.3 | 3.0.2 | **nein** |
| `symfony/polyfill-ctype` | v1.14.0 | v1.37.0 | ja |
| `symfony/polyfill-mbstring` | v1.14.0 | v1.38.2 | ja |

## Namensraum-Kollisionen im Autoloader

Dasselbe PSR-4-Präfix in beiden generierten Autoloadern. **Der Root wird zuerst geladen
und gewinnt** — unabhängig davon, welche Fassung gepflegt ist. Diese Kollisionen sind aus
der Paketliste allein nicht sichtbar.

| Präfix | Root | `custom/` |
|---|---|---|
| `Symfony\\Polyfill\\Mbstring` | `/symfony/polyfill-mbstring` | `/symfony/polyfill-mbstring` |
| `Symfony\\Polyfill\\Ctype` | `/symfony/polyfill-ctype` | `/symfony/polyfill-ctype` |
| `Psr\\Log` | `/psr/log/Psr/Log` | `/psr/log/src` |
| `PHPMailer\\PHPMailer` | `/phpmailer/phpmailer/src` | `/phpmailer/phpmailer/src` |

## Pakete mit einer oberen PHP-Grenze

Ein Constraint, der PHP 8.3 **nicht** einschliesst, macht das Paket unter Composer
uninstallierbar — auch wenn der eingefrorene Baum heute läuft.

| Paket | Baum | `php`-Constraint | benutzt? |
|---|---|---|---|
| `doctrine/annotations` | Root | `^7.1` | **ja** |
| `doctrine/cache` | Root | `~7.1` | **ja** |
| `doctrine/collections` | Root | `^7.1.3` | **ja** |
| `doctrine/common` | Root | `^7.1` | **ja** |
| `doctrine/dbal` | Root | `^7.1` | **ja** |
| `doctrine/event-manager` | Root | `^7.1` | **ja** |
| `doctrine/inflector` | Root | `^7.1` | **ja** |
| `doctrine/instantiator` | Root | `^7.1` | **ja** |
| `doctrine/orm` | Root | `^7.1` | **ja** |
| `doctrine/persistence` | Root | `^7.1` | **ja** |
| `doctrine/reflection` | Root | `^7.1` | **ja** |
| `ellumilel/php-excel-writer` | Root | `^5.4\|^7.0` | — |
| `paragonie/random_compat` | Root | `^7` | — |
| `ramsey/uuid` | Root | `^5.4 \|\| ^7.0` | **ja** |
| `symfony/console` | Root | `^7.1.3` | **ja** |
| `symfony/contracts` | Root | `^7.1.3` | **ja** |
| `symfony/debug` | Root | `^7.1.3` | **ja** |
| `symfony/translation` | Root | `^7.1.3` | **ja** |
| `symfony/validator` | Root | `^7.1.3` | **ja** |
| `twig/twig` | Root | `^7.0` | **ja** |

> Diese Liste ist eine **Vorauswahl anhand der Zeichenkette**, kein Composer-Urteil.
> Ob der Ist-Stack auflösbar ist, beantwortet `006-001-0003` mit einem echten
> Auflösungslauf.

## Was Silex pinnt — und was sonst noch im Baum liegt

Epic `006` ist darauf zugeschnitten, dass der **Ist-Stack** auflösbar bleibt. Der
Epic-Text begründet das damit, Silex und die Symfony-3.4-Komponenten hätten nach oben
offene `php`-Constraints. Diese Tabelle prüft genau das — und trennt dabei, was der
Epic-Text zusammenwirft: die vier von Silex **gepinnten** Pakete und den Rest.

**Von `silex/silex` gepinnt** (`php: >=5.5.9`):

| Paket | Silex verlangt | eigener `php`-Constraint | nach oben offen? |
|---|---|---|---|
| `symfony/event-dispatcher` | `~2.8\|^3.0` | `^5.5.9\|>=7.0.8` | ja |
| `symfony/http-foundation` | `~2.8\|^3.0` | `^5.5.9\|>=7.0.8` | ja |
| `symfony/http-kernel` | `~2.8\|^3.0` | `^5.5.9\|>=7.0.8` | ja |
| `symfony/routing` | `~2.8\|^3.0` | `^5.5.9\|>=7.0.8` | ja |

**Weitere Symfony-Pakete im Root-Baum**, die Silex *nicht* pinnt:

| Paket | `php`-Constraint | nach oben offen? |
|---|---|---|
| `symfony/console` | `^7.1.3` | **nein** |
| `symfony/contracts` | `^7.1.3` | **nein** |
| `symfony/debug` | `^7.1.3` | **nein** |
| `symfony/polyfill-ctype` | `>=5.3.3` | ja |
| `symfony/polyfill-mbstring` | `>=5.3.3` | ja |
| `symfony/polyfill-php56` | `>=5.3.3` | ja |
| `symfony/polyfill-php70` | `>=5.3.3` | ja |
| `symfony/polyfill-util` | `>=5.3.3` | ja |
| `symfony/translation` | `^7.1.3` | **nein** |
| `symfony/validator` | `^7.1.3` | **nein** |

