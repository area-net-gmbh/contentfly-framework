---
id: 000-000-0022
title: Sieben implizit nullable Parameter im eigenen Code
status: todo
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
- [ ] Alle sieben Stellen tragen einen expliziten `?`-Typ, oder eine Ausnahme ist begründet.
- [ ] Die Verträglichkeit von `ContentflyQuoteStrategy::getColumnAlias()` mit der
      Doctrine-Basisklasse ist geprüft.
- [ ] Der Testlauf auf `php:8.4-cli` meldet danach **eine** Deprecation statt zwei — die aus
      Silex.
- [ ] Die Suite bleibt bei ihren bekannten Failures; keine Verschiebung.

## Verification
Der vollständige Job in Docker auf **beiden** Pipeline-Images, wie in `000-000-0021`:

```sh
# 8.3: unveraendert 0 Deprecations, Suite wie gehabt
# 8.4: genau 1 Deprecation (Silex), Suite wie gehabt
```

Dazu die Gegenprobe über den Baum: Das `grep`-Muster aus dem Context darf keinen Treffer mehr
liefern — ausser an einer begründeten Ausnahme.
