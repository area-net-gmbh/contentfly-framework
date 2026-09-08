---
id: 006-001-0000
title: Abhängigkeiten inventarisieren und zuordnen
status: done
depends_on: []
---

# Abhängigkeiten inventarisieren und zuordnen

## Goal
Bevor ein Root-Manifest entstehen kann, muss feststehen, **was überhaupt da ist und wohin es
gehört**. Diese Story liefert kein Manifest, sondern die Entscheidungsgrundlage dafür: eine
vollständige Zuordnung beider Vendor-Bäume nach Root, `custom/`, `require-dev` oder „entfällt" —
je mit Begründung.

Das ist mehr als Buchhaltung. Die Zuordnung prägt die Vorlage, an der sich jedes migrierende
Projekt orientiert (Epic `007`): Was im Root steht, ist Framework-Sache und wird mitgeliefert;
was in `custom/` steht, verantwortet das Projekt.

## Umfang

### Ausgangslage
87 Pakete in zwei Bäumen: 39 im Root (`vendor/composer/installed.json`), 48 unter `custom/`.
Der Root-Baum hat **kein** `composer.json` — er wurde eingefroren und in Git gelegt.

### A — Die PHP-8-Blocker vollständig erfassen
Das Epic nennt nur `ellumilel/php-excel-writer`. Tatsächlich cappen **vier** Pakete auf PHP 7:

| Paket | `php`-Constraint | im Code benutzt |
|---|---|---|
| `ellumilel/php-excel-writer` v0.1.6 | `^5.4\|^7.0` | nein |
| `twig/twig` v2.4.4 | `^7.0` | nein |
| `ramsey/uuid` 3.8.0 | `^5.4 \|\| ^7.0` | **ja** — ID-Strategie `UUID` |
| `doctrine/orm` `dev-bugfix-many2many` | `^7.1` | **ja** |

Die ersten beiden entfallen ersatzlos (Excel-Export und Oberfläche sind mit Epic `012` weg). Die
letzten beiden brauchen ein Ziel-Release — `ramsey/uuid ^4` und eine releaste Doctrine-Version.
`silex/silex` und die Symfony-3.4-Komponenten sind entgegen der Erwartung **nicht** blockierend:
ihre `php`-Constraints sind nach oben offen. Sie fallen aus einem anderen Grund — sie sind EOL und
stehen dem Ziel-Kernel im Weg.

### B — Die Geisterpakete
Zwei Verzeichnisse in `vendor/` stehen in **keiner** `installed.json`, sind aber im Autoloader
registriert:

- `vendor/scssphp` — `ScssPhp\ScssPhp\` in `vendor/composer/autoload_psr4.php`
- `vendor/phpmailer` — `PHPMailer\PHPMailer\` ebenda

Sie wurden von Hand hineinkopiert. Beide gehören in die Zuordnung wie jedes andere Paket auch —
mit dem Vermerk, dass Composer von ihnen nichts weiß.

### C — Die toten `ScssPhp`-Importe entfernen
`lib/contentfly/bootstrap.php` importiert `ScssPhp\ScssPhp\Compiler` und
`ScssPhp\ScssPhp\OutputStyle`. Beides sind nur `use`-Zeilen ohne Verwendung — ein Rest der
SCSS-Kompilierung für die gelöschte Oberfläche, den Epic `012` übersehen hat. **Sie fallen hier**,
denn solange sie stehen, sieht `scssphp` wie eine echte Abhängigkeit aus und blockiert die
Zuordnung.

Das ist der einzige Codeeingriff dieser Story.

### D — Die Grenzfälle entscheiden
Vier Pakete liegen heute in `custom/`, gehören aber möglicherweise ins Framework. Die
Entscheidung ist zu **begründen**, nicht zu setzen — sie prägt alle Folgeprojekte:

| Paket | Vorschlag | Begründung |
|---|---|---|
| `firebase/php-jwt` | Root | Epic `013` baut JWT in das Framework ein, nicht in ein Projekt. |
| `vlucas/phpdotenv` | Root | Konfiguration über Umgebungsvariablen ist Infrastruktur jedes Projekts. |
| `sentry/sentry` | Root | dito — Fehler-Reporting gehört zum Betrieb, nicht zur Fachlichkeit. |
| `onelogin/php-saml` | `custom/` | Ein SAML-Identity-Provider ist die Entscheidung eines konkreten Kunden. |

`phpmailer` gehört ins Root: das Framework verschickt selbst Mails (`MAILER_*` in
`Classes/Config.php`). Damit ist zugleich die Dublette entschieden.

### E — Die Dubletten benennen
Drei Pakete liegen in beiden Bäumen in inkompatiblen Majors:

| Paket | Root | `custom/` |
|---|---|---|
| `psr/log` | 1.1.3 | 3.0.2 |
| `symfony/polyfill-ctype` | v1.14.0 | v1.37.0 |
| `symfony/polyfill-mbstring` | v1.14.0 | v1.38.2 |

Dazu die **vierte, unsichtbare** Dublette: `PHPMailer\PHPMailer\` ist in beiden Autoloadern
registriert (Root `autoload_psr4.php:43`, `custom/` `:20`). Der Root wird zuerst geladen — also
gewinnt die von Hand hineinkopierte Fassung über das gepflegte `^6.10` aus `custom/`. Aufgelöst
wird das in Story `006-004`; hier wird es erfasst.

### F — Dev-Tools trennen
`phpstan/phpstan` und `rector/rector` liegen heute im Root-**Produktions**baum, `phpunit/phpunit`
und `mockery/mockery` in `custom/`. Alle vier gehören in `require-dev` des Roots.

## Fertig, wenn
- Eine Tabelle in `an_project/docs/` ordnet **jedes** der 87 Pakete einer der vier Kategorien zu:
  Root, `custom/`, `require-dev`, entfällt — je mit einem Satz Begründung.
- Die vier PHP-8-Blocker sind benannt, je mit Ziel-Release oder dem Vermerk „entfällt".
- Die beiden Geisterpakete sind als solche gekennzeichnet.
- Die vier Grenzfälle sind entschieden und begründet; die Begründung taugt als Vorlage für
  Epic `007`.
- Die vier Dubletten sind erfasst, inklusive der über den Autoloader.
- Die toten `ScssPhp`-Importe in `bootstrap.php` sind entfernt und die Anwendung bootet weiterhin.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 006-001-0001 — Die tote SCSS-Spur entfernen
- [x] 006-001-0002 — Beide Vendor-Bäume vollständig erfassen
- [x] 006-001-0003 — Belegen, dass der Ist-Stack auflösbar ist
- [x] 006-001-0004 — Zuordnung entscheiden und begründen
