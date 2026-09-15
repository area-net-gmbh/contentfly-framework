---
id: 000-000-0037
title: Importe auf Klassen, die es nicht gibt
status: todo
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
- [ ] Die `@throws`-Angaben in `Api.php` zeigen auf Klassen, die es gibt: `Doctrine\Persistence\Mapping\MappingException` und `Doctrine\ORM\Exception\ORMException`.
- [ ] Die drei ungenutzten Importe in `Factory.php`, `SystemController.php` und `bootstrap-web.php` sind entfernt.
- [ ] Ein Test hält fest, dass kein `use`-Import unter `lib/contentfly/`, `custom/` und `bin/` auf eine nicht existierende Klasse zeigt — mit Gegenprobe, dass er einen solchen Import meldet.
- [ ] Volle Suite grün, PHPStan ohne Fehler, Deprecation-Gate 0.

## Verification
Den Scan vorher (fünf Treffer) und nachher (null) laufen lassen. Den neuen Test gegen einen
absichtlich eingesetzten toten Import laufen lassen. Volle Suite, PHPStan.
