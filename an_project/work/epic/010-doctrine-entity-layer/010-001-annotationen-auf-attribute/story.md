---
id: 010-001-0000
title: Annotationen auf PHP-Attribute — noch unter ORM 2.20
status: todo
depends_on: []
---

# Annotationen auf PHP-Attribute — noch unter ORM 2.20

## Goal
Die 202 Annotationen des Frameworks stehen als PHP-Attribute, und der Metadaten-Driver liest
sie über ORM 2.20s `AttributeDriver`. `doctrine/annotations` ist aus dem Baum, und mit ihm die
`AnnotationRegistry` — der Bootstrap, `TypeManager` und `Plugin.php` nennen sie nicht mehr.

**Die Version des ORM bleibt in dieser Story unverändert.** Das ist ihr ganzer Sinn: Die
Charakterisierungstests messen die Umstellung gegen ein ORM, das sich nicht bewegt. Was hier rot
wird, liegt an den Attributen — nicht an einem Versionssprung.

Betroffen sind drei Ebenen, und sie sind unterschiedlich schwer:

- **`@ORM\*`, 166 Stellen in 21 Entities.** Mechanisch, aber nicht blind: `Entity\Base` trägt
  `strategy=APPCMS_ID_STRATEGY` und `type=APPCMS_ID_TYPE`. Als Attribut ist eine globale
  Konstante ein zulässiger konstanter Ausdruck — die Konfigurierbarkeit bleibt also erhalten,
  aber der **Zeitpunkt** ist zu prüfen: Attribute werden beim Reflektieren ausgewertet, die
  Konstanten müssen dann definiert sein.
- **`@PIM\*`, 36 Stellen und acht Annotationsklassen.** Die Klassen brauchen `#[Attribute]` und
  einen Konstruktor; heute sind es Docblock-Klassen mit öffentlichen Feldern.
- **Die drei eigenen Lesestellen** in `Api.php`, `JoinBidirectionalType.php` und
  `ApiController.php`. Sie bauen sich einen `AnnotationReader` und müssen auf
  `ReflectionProperty::getAttributes()` umgestellt werden.

`custom/Entity/Core/Example.php` gehört dazu: Die Vorlage zeigt einem Bestandsprojekt, wie eine
Entity aussieht — bleibt sie auf Annotationen, lehrt sie das Falsche.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 010-001-0001 — Die acht @PIM-Klassen zu Attributklassen erweitern
- [ ] 010-001-0002 — Den Zugriff auf die eigenen Metadaten an einer Stelle bündeln
- [ ] 010-001-0003 — Die 21 Framework-Entities auf Attribute umstellen
- [ ] 010-001-0004 — Die Vorlage umstellen und den Plugin-Pfad entscheiden
- [ ] 010-001-0005 — doctrine/annotations aus dem Baum nehmen
