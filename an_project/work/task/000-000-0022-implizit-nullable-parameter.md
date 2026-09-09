---
id: 000-000-0022
title: Sieben implizit nullable Parameter im eigenen Code
status: review
depends_on: [006-005-0003]
---

# Sieben implizit nullable Parameter im eigenen Code

## Context
Gefunden mit `006-005-0003` beim Bauen des „0 Deprecations"-Gates. Der Testlauf auf PHP 8.4
protokolliert zwei Deprecations, eine davon im eigenen Code:

```
Deprecated: Areanet\PIM\Classes\Config::__construct(): Implicitly marking parameter
$config as nullable is deprecated, the explicit nullable type must be used instead
```

**Die Zahl im Log ist irreführend.** Sie zählt, was der Testlauf *ausgeführt* hat, nicht was im
Baum steht. Ein `grep` über `lib/`, `custom/` und `bin/` findet **sieben** Stellen desselben
Musters — ein Typ mit `= null`, ohne führendes `?`:

| Datei | Zeile |
|---|---|
| `lib/contentfly/Classes/Api.php` | `__construct($app, $request = null, User $user = null)` |
| `lib/contentfly/Classes/Config.php` | `__construct($host = 'default', Config $config = null)` |
| `lib/contentfly/Classes/Manager/LoginManager.php` | `__construct(Application $app, Request $request = null)` |
| `lib/contentfly/Classes/Manager/LoginManager.php` | `createManagedUser($alias, Group $group = null, …)` |
| `lib/contentfly/Classes/ORM/Mapping/ContentflyQuoteStrategy.php` | `getColumnAlias(…, ClassMetadata $class = null)` |
| `lib/contentfly/Entity/Serializable.php` | eine Stelle |
| `lib/contentfly/Entity/User.php` | eine Stelle |

Nur die zweite wird im Testlauf erreicht. Die übrigen sechs sind still — bis ein anderer Pfad
läuft.

## Umfang
Aus `Typ $x = null` wird `?Typ $x = null`. Die Syntax gibt es seit PHP 7.1, sie ist also mit
`platform.php: 8.3` und mit jedem Zielstand verträglich.

**Alle sieben oder keine.** Eine von sieben zu beheben — die zufällig im Log auftauchte — wäre
willkürlich und erzeugte ein falsches Bild: Das Gate meldete dann null, obwohl sechs gleichartige
Stellen weiterbestehen.

### Was dabei zu prüfen ist
`ContentflyQuoteStrategy::getColumnAlias()` überschreibt eine Doctrine-Methode. Ändert sich die
Signatur, muss sie zur Basisklasse passen — Doctrine 2.x deklariert dort selbst noch implizit
nullable. Ob `?ClassMetadata` mit der geerbten Signatur verträglich ist, gehört geprüft, nicht
angenommen; im Zweifel bleibt diese eine Stelle mit einer Begründung stehen.

## Abgrenzung
Keine anderen Deprecations. Insbesondere **nicht** die aus `Silex\Application::run()` — die
liegt in einem fremden Paket und fällt mit Epic `009`.

Keine Änderung am Gate selbst; das steht mit `006-005-0003`.

## Acceptance criteria
- [x] Alle sieben Stellen tragen einen expliziten `?`-Typ, oder eine Ausnahme ist begründet.
- [x] Die Verträglichkeit von `ContentflyQuoteStrategy::getColumnAlias()` mit der
      Doctrine-Basisklasse ist geprüft.
- [x] Der Testlauf auf `php:8.4-cli` meldet danach **eine** Deprecation statt zwei — die aus
      Silex.
- [x] Die Suite bleibt bei ihren bekannten Failures; keine Verschiebung.

## Verification
Der vollständige Job in Docker auf **beiden** Pipeline-Images, wie in `000-000-0021`:

```sh
# 8.3: unveraendert 0 Deprecations, Suite wie gehabt
# 8.4: genau 1 Deprecation (Silex), Suite wie gehabt
```

Dazu die Gegenprobe über den Baum: Das `grep`-Muster aus dem Context darf keinen Treffer mehr
liefern — ausser an einer begründeten Ausnahme.

## Ergebnis
**Es waren fünf Stellen, nicht sieben.** Nach der Korrektur meldet PHP 8.4 **keine einzige**
Implicitly-marking-Deprecation mehr aus eigenem Code.

### Die Zahl im Task war falsch — meine eigene
Der Task nennt sieben Stellen und listet `Entity/Serializable.php` und `Entity/User.php` mit je
einer. **Beide tragen längst `?Application $app = null`**, also den expliziten Typ. Mein
damaliger Zählausdruck war nicht verankert und traf die Zeile auch mit führendem `?`.

Die tatsächlichen fünf:

| Datei | Parameter |
|---|---|
| `Classes/Config.php` | `?Config $config` |
| `Classes/Api.php` | `?User $user` |
| `Classes/Manager/LoginManager.php` | `?Request $request` |
| `Classes/Manager/LoginManager.php` | `?Group $group` |
| `Classes/ORM/Mapping/ContentflyQuoteStrategy.php` | `?ClassMetadata $class` |

Nach der Änderung findet ein verankerter `grep` über `lib/`, `custom/` und `bin/` **keine
weitere** Stelle.

### `ContentflyQuoteStrategy` war nicht nur verträglich, sondern abweichend
Der Task hiess, die Verträglichkeit mit der Doctrine-Basisklasse sei zu prüfen und im Zweifel
eine Ausnahme zu begründen. Nachgesehen:

```php
// vendor/doctrine/orm/src/Mapping/QuoteStrategy.php:84 — die Schnittstelle
public function getColumnAlias($columnName, $counter, AbstractPlatform $platform, ?ClassMetadata $class = null);
```

**Doctrine deklariert selbst `?ClassMetadata`.** Unsere Implementierung wich also von der
Schnittstelle ab, die sie implementiert. Der Zweifel des Tasks löst sich damit in die
Gegenrichtung auf: Die Änderung stellt die Übereinstimmung her, statt sie zu gefährden.

### Was PHP 8.4 jetzt meldet
Der vollständige Job im Image `php:8.4-cli`, mit MySQL-Service:

| | vorher | nachher |
|---|---|---|
| Paare aus Datei und Meldung | 50 | **47** |
| davon ohne Ausnahme | 46 | **43** |
| davon aus eigenem Code | 3 | **0** |

Die 43 verbliebenen liegen in **19 Dateien, alle unter `vendor/`** — Silex, `symfony/debug`,
`symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing`. Sie fallen mit Epic `009`.

> **Auch die Erwartung des Tasks stimmte nicht.** Er sagte, danach bleibe „eine Deprecation,
> die aus Silex". Es sind 43, und die Zahl 2 aus dem ursprünglichen Befund war am Job-Log
> gemessen statt am Serverlog — derselbe Messfehler, den `006-005-0003` bereits korrigiert hat.
> Was stimmt, ist die Richtung: aus eigenem Code kommt nichts mehr.

### Verification
| Prüfung | Ergebnis |
|---|---|
| Suite auf PHP 8.3 (Pflicht-Job) | **OK (249 tests, 605 assertions)**, 0 übersprungen |
| Deprecation-Gate auf 8.3 | grün, 4 Paare, 4 ausgenommen — unverändert |
| Suite auf PHP 8.4 | **OK (249 tests, 605 assertions)** |
| Implicitly-marking aus `lib/`, `custom/`, `bin/` auf 8.4 | **0** |

Auf 8.3 ändert sich erwartungsgemäss nichts: Implizit nullable Parameter sind erst ab 8.4
deprecated. Die Änderung wirkt dort vorbeugend.
