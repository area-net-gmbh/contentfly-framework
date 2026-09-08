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

| Paket | Version | `php`-Constraint | Herkunft | benutzt? | Kategorie |
|---|---|---|---|---|---|
| `dflydev/doctrine-orm-service-provider` | v2.0.1 | `>=5.3.3` | installed.json | ja | **root** |
| `doctrine/annotations` | v1.8.0 | `^7.1` | installed.json | ja | **root** |
| `doctrine/cache` | 1.10.0 | `~7.1` | installed.json | ja | root |
| `doctrine/collections` | 1.6.4 | `^7.1.3` | installed.json | ja | root |
| `doctrine/common` | 2.12.0 | `^7.1` | installed.json | ja | root |
| `doctrine/dbal` | v2.6.3 | `^7.1` | installed.json | ja | **root** |
| `doctrine/event-manager` | 1.1.0 | `^7.1` | installed.json | ja | root |
| `doctrine/inflector` | 1.3.1 | `^7.1` | installed.json | ja | root |
| `doctrine/instantiator` | 1.3.0 | `^7.1` | installed.json | ja | root |
| `doctrine/lexer` | 1.0.2 | `>=5.3.2` | installed.json | ja | root |
| `doctrine/orm` | dev-bugfix-many2many | `^7.1` | installed.json | ja | **root** |
| `doctrine/persistence` | 1.3.7 | `^7.1` | installed.json | ja | root |
| `doctrine/reflection` | 1.2.0 | `^7.1` | installed.json | ja | root |
| `ellumilel/php-excel-writer` | v0.1.6 | `^5.4\|^7.0` | installed.json | — | **entfaellt** |
| `knplabs/console-service-provider` | v2.2.0 | `>=5.5.9` | installed.json | ja | **root** |
| `paragonie/random_compat` | v9.99.99 | `^7` | installed.json | — | **entfaellt** |
| `phpmailer/phpmailer` | (unbekannt) | `>=5.5.0` | **von Hand** | ja | **root** |
| `phpstan/phpstan` | 1.10.58 | `^7.2\|^8.0` | installed.json | — | **require-dev** |
| `pimple/pimple` | v3.2.3 | `>=5.3.0` | installed.json | — | **root** |
| `psr/cache` | 1.0.1 | `>=5.3.0` | installed.json | ja | root |
| `psr/container` | 1.0.0 | `>=5.3.0` | installed.json | ja | root |
| `psr/log` | 1.1.3 | `>=5.3.0` | installed.json | — | root |
| `ramsey/uuid` | 3.8.0 | `^5.4 \|\| ^7.0` | installed.json | ja | **root** |
| `rector/rector` | 1.0.1 | `^7.2\|^8.0` | installed.json | ja | **require-dev** |
| `scssphp/scssphp` | (unbekannt) | `>=5.6.0` | **von Hand** | — | **entfaellt** |
| `silex/silex` | v2.2.2 | `>=5.5.9` | installed.json | ja | **root** |
| `symfony/console` | v4.2.12 | `^7.1.3` | installed.json | ja | root |
| `symfony/contracts` | v1.1.8 | `^7.1.3` | installed.json | ja | root |
| `symfony/debug` | v4.4.5 | `^7.1.3` | installed.json | ja | root |
| `symfony/event-dispatcher` | v3.4.38 | `^5.5.9\|>=7.0.8` | installed.json | ja | root |
| `symfony/http-foundation` | v3.4.38 | `^5.5.9\|>=7.0.8` | installed.json | ja | root |
| `symfony/http-kernel` | v3.4.38 | `^5.5.9\|>=7.0.8` | installed.json | ja | root |
| `symfony/polyfill-ctype` | v1.14.0 | `>=5.3.3` | installed.json | ja | root |
| `symfony/polyfill-mbstring` | v1.14.0 | `>=5.3.3` | installed.json | ja | root |
| `symfony/polyfill-php56` | v1.14.0 | `>=5.3.3` | installed.json | ja | **entfaellt** |
| `symfony/polyfill-php70` | v1.14.0 | `>=5.3.3` | installed.json | ja | **entfaellt** |
| `symfony/polyfill-util` | v1.14.0 | `>=5.3.3` | installed.json | ja | **entfaellt** |
| `symfony/routing` | v3.4.38 | `^5.5.9\|>=7.0.8` | installed.json | ja | root |
| `symfony/translation` | v4.3.11 | `^7.1.3` | installed.json | ja | root |
| `symfony/validator` | v4.0.4 | `^7.1.3` | installed.json | ja | root |
| `twig/twig` | v2.4.4 | `^7.0` | installed.json | ja | **entfaellt** |

## custom/ — 48 Pakete

| Paket | Version | `php`-Constraint | Herkunft | benutzt? | Kategorie |
|---|---|---|---|---|---|
| `firebase/php-jwt` | v7.1.0 | `^8.0` | installed.json | — | **entfaellt** |
| `graham-campbell/result-type` | v1.1.4 | `^7.2.5 \|\| ^8.0` | installed.json | — | root |
| `guzzlehttp/psr7` | 2.12.5 | `^7.2.5 \|\| ^8.0` | installed.json | — | entfaellt |
| `hamcrest/hamcrest-php` | v2.1.1 | `^7.4\|^8.0` | installed.json | — | entfaellt |
| `jean85/pretty-package-versions` | 2.1.1 | `^7.4\|^8.0` | installed.json | — | entfaellt |
| `mockery/mockery` | 1.6.12 | `>=7.3` | installed.json | — | **entfaellt** |
| `myclabs/deep-copy` | 1.13.4 | `^7.1 \|\| ^8.0` | installed.json | — | require-dev |
| `nikic/php-parser` | v5.7.0 | `>=7.4` | installed.json | — | require-dev |
| `onelogin/php-saml` | 4.3.2 | `>=7.3` | installed.json | — | **entfaellt** |
| `phar-io/manifest` | 2.0.4 | `^7.2 \|\| ^8.0` | installed.json | — | require-dev |
| `phar-io/version` | 3.2.1 | `^7.2 \|\| ^8.0` | installed.json | ja | require-dev |
| `phpmailer/phpmailer` | v6.10.0 | `>=5.5.0` | installed.json | ja | **root** |
| `phpoption/phpoption` | 1.9.5 | `^7.2.5 \|\| ^8.0` | installed.json | — | root |
| `phpunit/php-code-coverage` | 10.1.16 | `>=8.1` | installed.json | — | require-dev |
| `phpunit/php-file-iterator` | 4.1.0 | `>=8.1` | installed.json | — | require-dev |
| `phpunit/php-invoker` | 4.0.0 | `>=8.1` | installed.json | — | require-dev |
| `phpunit/php-text-template` | 3.0.1 | `>=8.1` | installed.json | — | require-dev |
| `phpunit/php-timer` | 6.0.0 | `>=8.1` | installed.json | — | require-dev |
| `phpunit/phpunit` | 10.5.63 | `>=8.1` | installed.json | — | **require-dev** |
| `psr/http-factory` | 1.1.0 | `>=7.1` | installed.json | — | entfaellt |
| `psr/http-message` | 2.0 | `^7.2 \|\| ^8.0` | installed.json | — | entfaellt |
| `psr/log` | 3.0.2 | `>=8.0.0` | installed.json | — | entfaellt |
| `ralouphie/getallheaders` | 3.0.3 | `>=5.6` | installed.json | — | entfaellt |
| `robrichards/xmlseclibs` | 3.1.5 | `>= 5.4` | installed.json | — | **entfaellt** |
| `sebastian/cli-parser` | 2.0.1 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/code-unit` | 2.0.0 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/code-unit-reverse-lookup` | 3.0.0 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/comparator` | 5.0.5 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/complexity` | 3.2.0 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/diff` | 5.1.1 | `>=8.1` | installed.json | ja | require-dev |
| `sebastian/environment` | 6.1.0 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/exporter` | 5.1.4 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/global-state` | 6.0.2 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/lines-of-code` | 2.0.2 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/object-enumerator` | 5.0.0 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/object-reflector` | 3.0.0 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/recursion-context` | 5.0.1 | `>=8.1` | installed.json | — | require-dev |
| `sebastian/type` | 4.0.0 | `>=8.1` | installed.json | ja | require-dev |
| `sebastian/version` | 4.0.1 | `>=8.1` | installed.json | ja | require-dev |
| `sentry/sentry` | 4.22.0 | `^7.2\|^8.0` | installed.json | — | **entfaellt** |
| `stripe/stripe-php` | v18.2.0 | `>=5.6.0` | installed.json | ja | **entfaellt** |
| `symfony/deprecation-contracts` | v3.6.0 | `>=8.1` | installed.json | ja | entfaellt |
| `symfony/options-resolver` | v7.4.0 | `>=8.2` | installed.json | ja | entfaellt |
| `symfony/polyfill-ctype` | v1.37.0 | `>=7.2` | installed.json | ja | root |
| `symfony/polyfill-mbstring` | v1.38.2 | `>=7.2` | installed.json | ja | root |
| `symfony/polyfill-php80` | v1.37.0 | `>=7.2` | installed.json | ja | root |
| `theseer/tokenizer` | 1.3.1 | `^7.2 \|\| ^8.0` | installed.json | — | require-dev |
| `vlucas/phpdotenv` | v5.6.4 | `^7.2.5 \|\| ^8.0` | installed.json | — | **root** |

## Zuordnung

Entschieden mit `006-001-0004`. **Was im Root steht, ist Framework-Sache und wird
mitgeliefert; was in `custom/` steht, verantwortet das Projekt.**

### Das Prinzip — woran sich ein neues Paket entscheiden lässt

| Frage | Antwort |
|---|---|
| Benutzt `lib/` es? | **root** |
| Benutzt die ausgelieferte Vorlage es (`custom/config.php`, `custom/app.php`)? | **root** — sie gehört zum Framework |
| Benutzt nur Projektcode es? | **custom** |
| Nur Tests oder Werkzeuge? | **require-dev** |
| Niemand? | **entfällt** |

Die Reihenfolge zählt: Die erste zutreffende Zeile gewinnt.

### Kontrollsumme

| Kategorie | Pakete |
|---|---|
| root | 39 |
| custom | 0 |
| require-dev | 28 |
| entfaellt | 22 |
| **Summe** | **89** |

Die Summe muss der Gesamtzahl oben entsprechen — ein Paket ohne Kategorie ist ein
übersehenes Paket.

### Die ausdrücklich entschiedenen Fälle

Alle übrigen tragen eine **abgeleitete** Kategorie: im Root-Baum `root` (transitive
Abhängigkeit des Ist-Stacks), unter `custom/` `entfaellt` (transitive Abhängigkeit eines
gestrichenen Pakets). In der Tabelle oben sind die entschiedenen **fett** gesetzt.

#### root

- **`dflydev/doctrine-orm-service-provider`** — Bindet Doctrine in den Silex-Container. Faellt mit Epic 009.
- **`doctrine/annotations`** — Die @PIM- und @ORM-Annotationen haengen daran. Abandoned — Ersatz gehoert zu Epic 010.
- **`doctrine/dbal`** — Datenbankschicht unter dem ORM. Bleibt vorerst bei 2.x, um den API-Bruch von DBAL 3 zu vermeiden.
- **`doctrine/orm`** — Der Entity-Layer. Wechselt von einem eigenen Fork (area-net-gmbh/doctrine2, 2018) auf ein Release — siehe 006-001-0003.
- **`knplabs/console-service-provider`** — Bindet die Console in den Silex-Container; deckelt symfony/console auf ^4.0. Faellt mit Epic 009.
- **`phpmailer/phpmailer`** — lib/contentfly/Classes/Mailer.php benutzt es; die MAILER_*-Felder stehen in Classes/Config.php. Loest zugleich die Autoloader-Dublette auf — die von Hand kopierte Root-Fassung faellt weg, das gepflegte ^6.10 bleibt.
- **`pimple/pimple`** — Der DI-Container von Silex. Faellt mit Epic 009.
- **`ramsey/uuid`** — Traegt die ID-Strategie UUID (APPCMS_ID_STRATEGY in bootstrap.php). Muss von 3.8 auf ^4, weil 3.8 auf PHP 7 cappt.
- **`silex/silex`** — Der Kernel. 26 Dateien haengen daran. Faellt mit Epic 009.
- **`vlucas/phpdotenv`** — lib/ benutzt es nicht, aber die ausgelieferte custom/config.php tut es — und die gehoert zum Framework. Der dortige class_exists-Schutz war nur noetig, weil das Paket in custom/ lag und fehlen konnte; im Root eruebrigt er sich.

#### require-dev

- **`phpstan/phpstan`** — Statische Analyse. Liegt heute im Root-PRODUKTIONSbaum — ein Werkzeug, das in kein Deployment-Artefakt gehoert.
- **`rector/rector`** — Automatisierte Refactorings; Epic 007 baut darauf seine Migrationsregeln. Liegt heute ebenfalls im Produktionsbaum.
- **`phpunit/phpunit`** — Die Testsuite. Wandert von custom/ ins Root — der Pfad in .gitlab-ci.yml steht als Variable an einer Stelle, damit das ein Einzeiler bleibt.

#### entfaellt

- **`ellumilel/php-excel-writer`** — Konsument war der ExportController, geloescht mit 012-001-0003. Cappt zudem auf php ^5.4|^7.0 und ist damit unter PHP 8 ohnehin nicht installierbar.
- **`paragonie/random_compat`** — Polyfill fuer random_bytes() unter PHP 5. Unter PHP 8 ueberfluessig.
- **`scssphp/scssphp`** — Konsument war der SCSS-Compiler in bootstrap.php, entfernt mit 006-001-0001. Geisterpaket: stand in keiner installed.json, nur im Autoloader.
- **`symfony/polyfill-php56`** — Polyfill fuer PHP 5.6. Unter PHP 8 ueberfluessig.
- **`symfony/polyfill-php70`** — Polyfill fuer PHP 7.0. Unter PHP 8 ueberfluessig.
- **`symfony/polyfill-util`** — Hilfsschicht der aelteren Polyfills; faellt mit ihnen.
- **`twig/twig`** — Konsument war die PIM-Oberflaeche, geloescht mit Epic 012. Cappt auf php ^7.0. Der einzige verbliebene Treffer im Baum ist das Wort Twig in einem Kommentar von Command/InstallCommand.php.
- **`firebase/php-jwt`** — Wird heute nirgends benutzt. Epic 013 baut JWT ein und nimmt es dann wieder auf — ein Manifest beschreibt, was gebraucht wird, nicht was gebraucht werden koennte.
- **`mockery/mockery`** — Steht in custom/composer.json unter require-dev, wird aber in keinem Test benutzt — die Suite aus Epic 008 kommt ohne Mocking-Framework aus. Wer es braucht, nimmt es wieder auf.
- **`onelogin/php-saml`** — Wird nirgends benutzt. Ein SAML-Identity-Provider ist die Entscheidung eines konkreten Kunden, nicht die des Frameworks.
- **`robrichards/xmlseclibs`** — Transitive Abhaengigkeit von onelogin/php-saml (^3.1.5) und faellt mit ihm.
- **`sentry/sentry`** — Wird nirgends benutzt. Rest aus der Kundenanwendung, aus der die Vorlage geschnitten wurde. Ein Projekt, das Fehler-Reporting will, traegt es in sein eigenes custom/composer.json ein.
- **`stripe/stripe-php`** — Wird nirgends benutzt. Der einzige Treffer im Baum ist ein Kommentar in custom/Entity/Core/Example.php — genau einer der projektfremden Kommentare aus 000-000-0017.

## Geisterpakete

Verzeichnisse, die in **keiner** `installed.json` stehen. Von Hand hineinkopiert;
Composer weiss von ihnen nichts, der Autoloader schon.

- `phpmailer/phpmailer` (in Root)
- `scssphp/scssphp` (in Root)

## Dubletten — dasselbe Paket in beiden Bäumen

**Die Kategorie gilt dem Paket, nicht der Version.** Steht eine Dublette einmal auf
`root` und einmal auf `entfaellt`, heisst das: Das Paket bleibt, *diese Fassung* nicht.
Welche Version das neue Manifest bekommt, entscheidet Composer beim Auflösen — bei
`psr/log` etwa weder 1.1.3 noch 3.0.2, sondern 2.0.0 (`006-001-0003`).

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

