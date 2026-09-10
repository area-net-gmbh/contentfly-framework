---
id: 010-001-0001
title: Die acht @PIM-Klassen zu Attributklassen erweitern
status: done
depends_on: []
---

# Die acht @PIM-Klassen zu Attributklassen erweitern

## Context
Die acht Annotationsklassen unter `Classes/Annotations/` sind heute reine Docblock-Klassen mit
öffentlichen Feldern: `Checkbox`, `Config`, `I18nPermissions`, `ManyToMany`, `Permissions`,
`Radio`, `Select`, `Virtualjoin`. Damit sie als PHP-Attribut geschrieben werden können, brauchen
sie `#[Attribute]` und einen Konstruktor.

**Dieser Task ist rein additiv.** Eine Klasse kann beides sein — Doctrine-Annotation und
PHP-Attribut —; Doctrines eigene Mapping-Klassen sind in 2.20 genau das. Solange die Entities
noch Docblocks tragen, liest sie weiterhin der `AnnotationReader`, und nichts ändert sich am
Verhalten. Der Task legt nur die Voraussetzung dafür, dass `010-001-0003` überhaupt umstellen
kann.

`@PIM\Config` ist die mit Abstand wichtigste — 25 der 36 Verwendungen — und die einzige, die
sowohl an Klassen als auch an Properties steht. `Attribute::TARGET_*` muss das abbilden.

## Acceptance criteria
- [x] Alle acht Klassen tragen `#[Attribute]` mit den zutreffenden Zielen (`TARGET_CLASS`, `TARGET_PROPERTY` oder beides) und einen Konstruktor mit benannten Parametern.
- [x] Die Vorgabewerte der öffentlichen Felder bleiben Wort für Wort erhalten — sie sind Teil des Schemas, das die Charakterisierungstests aus `008-004-0005` zusichern.
- [x] Die Klassen bleiben als Doctrine-Annotation lesbar: Die Suite ist unverändert grün, weil sich am Leseweg noch nichts geändert hat.
- [x] Jede Klasse ist auch dann noch instanziierbar, wenn ein Argument fehlt — heute darf jedes Feld weggelassen werden.

## Verification
Volle Suite. Zusätzlich ein gezielter Nachweis, dass beide Wege funktionieren: eine Wegwerf-Klasse
mit `#[Areanet\PIM\Classes\Annotations\Config(...)]` per Reflection auslesen und dasselbe Objekt
erhalten wie über den `AnnotationReader` aus einem Docblock.

## Ergebnis

**Alle acht Klassen sind Annotation und Attribut zugleich, und beide Wege liefern nachweislich
dasselbe Objekt.** Die Suite ist unverändert grün, weil sich am Leseweg noch nichts geändert hat.

### Der Vermerk, ohne den es nicht geht

`@NamedArgumentConstructor` ist keine Zierde. Der `DocParser` entscheidet an
`has_constructor`, wie er eine Annotation baut: **ohne** den Vermerk übergibt er dem Konstruktor
**ein Array** — also `new Select(['options' => 'a,b'])` gegen eine Signatur, die einen String
erwartet. Die Klassen wären als Annotation sofort unlesbar gewesen, und zwar erst beim ersten
Metadaten-Zugriff, nicht beim Laden.

Bei `Permissions` und `I18nPermissions` steht der Vermerk **nicht**: Sie haben keine Felder,
brauchen keinen Konstruktor, und `DocParser` knüpft `has_named_argument_constructor` an
`has_constructor` — der Vermerk wäre dort wirkungslos und damit irreführend.

### Keine Typangaben, mit Absicht

Die Konstruktor-Parameter sind untypisiert. Unter Annotationen war jedes Feld untypisiert; ein
hier ergänzter Typ wäre eine **neue Einschränkung für Bestandsprojekte**, nicht bloss eine
Präzisierung — ein Projekt mit `@PIM\Config(unique=1)` bekäme einen `TypeError`, wo es
jahrelang funktioniert hat. Dieselbe Überlegung wie bei `Container::extend()` in `009-002-0002`.

`extends Annotation` ist entfallen. Die Basisklasse lieferte nur den Array-Konstruktor und
Magie für unbekannte Felder; beides wird nicht mehr gebraucht, und sie stammt aus dem Paket, das
`010-001-0005` entfernt. Kein `instanceof Annotation` im Baum — geprüft.

### Nachweis, beide Wege gegeneinander

Eine Probeklasse trägt jede Deklaration **doppelt**, als Docblock und als Attribut:

| Probe | Ergebnis |
|---|---|
| `Config` mit vier Argumenten, Annotation `==` Attribut | JA |
| `Select` mit einem Argument | JA |
| `Permissions` ohne Argument | JA |
| Nicht gesetzte Felder tragen ihre Vorgabe (`unique=false`, `type=''`) | JA |
| **Array-Schlüssel identisch** (`Areanet\PIM\Classes\Annotations\Select`) | JA |

Der letzte Punkt ist der wichtigste für die Folgetasks: 21 `Type`-Klassen greifen mit genau
dieser Schlüsselform auf `$propertyAnnotations` zu. `ReflectionAttribute::getName()` liefert
den kanonischen Klassennamen — also passt der Zugriff nach der Umstellung unverändert.

### Zwei Befunde am Rande

**`@PIM\Checkbox`, `@PIM\Radio` und `@PIM\ManyToMany` haben im Framework null Verwendungen.**
Gezählt über `lib/contentfly/Entity` und `custom`. Gelöscht wird trotzdem nichts:
`ManyToMany` wird an drei Stellen **gelesen** (`MultifileType`, `MultijoinType`,
`JoinBidirectionalType`) und ist dort das Unterscheidungsmerkmal, ob eine `OneToMany` als n:m
gilt; `Checkbox` und `Radio` wählen ihre `Type`-Klassen aus. Alle drei gehören Projekten, nicht
dem Framework. Der Befund steht als Kommentar an der jeweiligen Klasse.

**Die Attribut-Ziele sind enger als die Annotation-Ziele.** Ohne `@Target` galt für eine
Annotation `TARGET_ALL`; `#[Attribute(...)]` benennt jetzt Klasse, Property oder beides. Gemessen
passt das zur Verwendung im Baum — `Config` steht an Klassen **und** Properties, die übrigen nur
an Properties. Für ein Bestandsprojekt, das eine davon woanders gesetzt hat, wird die Einengung
mit `010-001-0003` wirksam; sie gehört dann nach `breaking-changes.md`.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (267 tests, 639 assertions)`, 0 übersprungen |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| PHPStan | `[OK] No errors` |
| Postausgang der Versandfalle | 0 Byte |
