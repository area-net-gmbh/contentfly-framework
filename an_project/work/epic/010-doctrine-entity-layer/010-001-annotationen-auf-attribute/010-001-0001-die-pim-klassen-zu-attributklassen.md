---
id: 010-001-0001
title: Die acht @PIM-Klassen zu Attributklassen erweitern
status: todo
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
- [ ] Alle acht Klassen tragen `#[Attribute]` mit den zutreffenden Zielen (`TARGET_CLASS`, `TARGET_PROPERTY` oder beides) und einen Konstruktor mit benannten Parametern.
- [ ] Die Vorgabewerte der öffentlichen Felder bleiben Wort für Wort erhalten — sie sind Teil des Schemas, das die Charakterisierungstests aus `008-004-0005` zusichern.
- [ ] Die Klassen bleiben als Doctrine-Annotation lesbar: Die Suite ist unverändert grün, weil sich am Leseweg noch nichts geändert hat.
- [ ] Jede Klasse ist auch dann noch instanziierbar, wenn ein Argument fehlt — heute darf jedes Feld weggelassen werden.

## Verification
Volle Suite. Zusätzlich ein gezielter Nachweis, dass beide Wege funktionieren: eine Wegwerf-Klasse
mit `#[Areanet\PIM\Classes\Annotations\Config(...)]` per Reflection auslesen und dasselbe Objekt
erhalten wie über den `AnnotationReader` aus einem Docblock.
