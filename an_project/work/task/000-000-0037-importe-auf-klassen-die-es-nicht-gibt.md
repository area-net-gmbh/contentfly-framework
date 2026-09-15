---
id: 000-000-0037
title: Importe auf Klassen, die es nicht gibt
status: done
depends_on: []
---

# Importe auf Klassen, die es nicht gibt

## Context
Aufgefallen bei `000-000-0031`: `Classes/Api.php` importiert
`Doctrine\Common\Persistence\Mapping\MappingException`, einen Namensraum, den es seit
`doctrine/persistence` 2.0 nicht mehr gibt. Ein `use` lädt nichts, deshalb fällt es nicht auf —
aber jedes `@throws MappingException` in `Api.php` zeigt damit ins Leere.

**Gemessen statt nur den einen Fall genommen:** Jeder `use`-Import unter `lib/contentfly/`,
`custom/`, `bin/` und `tests/` gegen den Autoloader geprüft (Klasse, Interface, Trait oder Enum;
Namensraum-Aliase wie `use Doctrine\ORM\Mapping as ORM` ausgenommen). Nach Abzug der Fehltreffer
— Importe in PHP-Strings von Tests und Rector-Fixtures — bleiben **fünf Importe in vier
Dateien**:

| Datei | Import | benutzt? |
|---|---|---|
| `Classes/Api.php` | `Doctrine\Common\Persistence\Mapping\MappingException` | nur in vier `@throws` |
| `Classes/Api.php` | `Doctrine\ORM\ORMException` | nur in drei `@throws`; in ORM 3 heisst es `Doctrine\ORM\Exception\ORMException` |
| `Classes/Config/Factory.php` | `Areanet\PIM\Classes\Exceptions\Config\NotFoundException` | nein; die Klasse heisst `FactoryNotFoundException` |
| `Controller/SystemController.php` | `Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper` | nein; in ORM 3 entfernt |
| `bootstrap-web.php` | `Areanet\PIM\Controller` | nein; weder Klasse noch als Namensraum benutzt |

PHPStan meldet keinen davon: Ein ungenutzter Import ist kein Fehler, und ein `@throws` auf eine
unbekannte Klasse prüft die aktuelle Konfiguration nicht.

**Bewusst nicht Teil dieses Tasks:** ungenutzte Importe auf Klassen, die es **gibt** (etwa
`UpdateCommand`, `HelperSet`, `SchemaValidator` in `SystemController`). Die sind Aufräumarbeit ohne
falsche Aussage; hier geht es um Verweise, die ins Leere zeigen.

## Acceptance criteria
- [x] Die `@throws`-Angaben in `Api.php` zeigen auf Klassen, die es gibt: `Doctrine\Persistence\Mapping\MappingException` und `Doctrine\ORM\Exception\ORMException`.
- [x] Die drei ungenutzten Importe in `Factory.php`, `SystemController.php` und `bootstrap-web.php` sind entfernt.
- [x] Ein Test hält fest, dass kein `use`-Import unter `lib/contentfly/`, `custom/` und `bin/` auf eine nicht existierende Klasse zeigt — mit Gegenprobe, dass er einen solchen Import meldet.
- [x] Volle Suite grün, PHPStan ohne Fehler, Deprecation-Gate 0.

## Verification
Den Scan vorher (fünf Treffer) und nachher (null) laufen lassen. Den neuen Test gegen einen
absichtlich eingesetzten toten Import laufen lassen. Volle Suite, PHPStan.

## Ergebnis

**Die fünf Importe sind nachgezogen:**

| Datei | vorher | nachher |
|---|---|---|
| `Classes/Api.php` | `Doctrine\Common\Persistence\Mapping\MappingException` | `Doctrine\Persistence\Mapping\MappingException` |
| `Classes/Api.php` | `Doctrine\ORM\ORMException` | `Doctrine\ORM\Exception\ORMException` |
| `Classes/Config/Factory.php` | `…\Exceptions\Config\NotFoundException` | entfernt |
| `Controller/SystemController.php` | `Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper` | entfernt |
| `bootstrap-web.php` | `Areanet\PIM\Controller` | entfernt |

In `Api.php` ist nur der Import geändert; die `@throws`-Zeilen lauten weiter `MappingException` und
`ORMException` und zeigen jetzt auf Klassen, die es gibt.

**Der Wächter:** `tests/Unit/DeadImportTest.php` prüft jeden `use`-Import auf Namensraum-Ebene
unter `lib/contentfly/`, `custom/` und `bin/` gegen den Autoloader. Gelesen wird mit dem
Tokenizer, nicht mit einem Muster.

**Der erste Entwurf hatte zwei Lücken, die der Lauf über den echten Baum aufgedeckt hat:**

- **Namensraum-Importe ohne Verwendung.** `use Areanet\PIM\Classes\Annotations as PIM;` steht in
  `BaseI18n`, `BaseSortable` und `BaseUID`, ohne dass `PIM\` dort vorkommt. Den Namensraum gibt es,
  der Import sagt also nichts Falsches. Der Entwurf prüfte nur „wird der Alias als Präfix
  benutzt?" und meldete ihn. Jetzt wird geprüft, ob der Namensraum unter einem PSR-4-Präfix des
  Composer-Autoloaders als Verzeichnis existiert.
- **Das `use($app)` einer Closure auf oberster Ebene.** `bootstrap-web.php` ist ein Skript, sein
  `$app->error(function (\Throwable $e) use($app) {…})` steht ausserhalb jeder Klammer und wurde
  als Import gelesen (Treffer: `404`). Ein `use`, dem eine Klammer folgt, wird jetzt übersprungen.

Beide Fälle stehen in der Gegenprobe `testTheScanReportsADeadImportAndNothingElse`, zusammen mit
einem toten Import, einem Trait-`use`, einer Closure in einer Methode und einem Import in einem
String. Erwartet wird genau der tote Import.

**Gegenprobe am echten Baum:** mit `Api.php` von master meldet der Wächter genau die zwei toten
Importe dort.

**Verifiziert:** volle Suite `Tests: 536, Assertions: 1748, Skipped: 3`, PHPStan
`[OK] No errors`, Deprecation-Gate 0. Der Scan aus dem Context meldet unter `lib/`, `custom/` und
`bin/` null Treffer (vorher fünf).

**Nicht angefasst, wie im Context abgegrenzt:** ungenutzte Importe auf Klassen, die es gibt — etwa
`UpdateCommand`, `HelperSet`, `SchemaValidator`, `ArrayInput`, `BufferedOutput` und `Application` in
`SystemController` sowie die ungenutzten `PIM`-Aliase in den drei Base-Entities.
