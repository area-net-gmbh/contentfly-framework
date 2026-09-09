---
id: 010-001-0003
title: Die 21 Framework-Entities auf Attribute umstellen
status: todo
depends_on: [010-001-0002]
---

# Die 21 Framework-Entities auf Attribute umstellen

## Context
Der eigentliche Schnitt: 166 `@ORM\*` und 36 `@PIM\*` werden zu Attributen, und der Treiber für
den Namensraum `Areanet\PIM\Entity` wechselt von `newDefaultAnnotationDriver()` auf
`AttributeDriver`.

**Warum das in einem Zug gehen muss:** Doctrine liest je Namensraum mit **einem** Treiber.
Ein halb umgestellter Namensraum hat kein Verhalten, das man messen könnte. Die Aufteilung nach
Namensräumen ist der Grund, warum die Vorlage einen eigenen Task bekommt (`010-001-0004`) — sie
liegt unter `Custom\Entity`.

Zu prüfen ist dabei ausdrücklich:

- **Die Konstanten.** `Base` trägt `strategy=APPCMS_ID_STRATEGY` und `type=APPCMS_ID_TYPE`. Als
  Attributargument ist eine globale Konstante zulässig — aber sie wird beim **Reflektieren**
  ausgewertet, nicht beim Laden der Datei. Der Zeitpunkt ist zu belegen, nicht anzunehmen.
- **`@ORM\CustomIdGenerator`** an `Base` und `Log`, dazugekommen mit `009-005-0002`.
- **Vererbung.** Vier `MappedSuperclass`, zwei `InheritanceType`. Attribute werden **nicht**
  vererbt; Doctrine löst das selbst auf, aber die eigenen Lesestellen tun es nicht
  zwangsläufig.
- **`@ORM\HasLifecycleCallbacks`** mit `PrePersist` und `PreUpdate`.

Die Umstellung ist eine Formatänderung, **keine Verhaltensänderung**: Jeder Wert bleibt, wie er
ist. Eine Abweichung im Schema wäre ein Befund.

## Acceptance criteria
- [ ] Alle 21 Entities unter `lib/contentfly/Entity/` tragen Attribute statt Docblock-Annotationen; kein `@ORM\` und kein `@PIM\` bleibt dort als Metadatum stehen (in Erklärungen dürfen die Namen weiter vorkommen).
- [ ] Der Treiber für `Areanet\PIM\Entity` ist ein `AttributeDriver`; die Chain aus `EntityManagerFactory` bleibt im Aufbau unverändert.
- [ ] Der Auswertungszeitpunkt der Konstanten ist **gemessen** belegt, nicht angenommen.
- [ ] `POST /api/config` liefert dasselbe Schema wie vorher — Feld für Feld verglichen, nicht überflogen.
- [ ] Die Suite ist grün, ohne eine einzige geänderte Zusicherung. Eine nötige Anpassung ist ein Befund und braucht eine Begründung.

## Verification
Schema-Vergleich: `POST /api/config` vor und nach der Umstellung als JSON sichern und
`diff`en — erwartet wird kein Unterschied. Dazu `orm:validate-schema` und die volle Suite. Die
neun ManyToMany-Tests aus `006-002-0001` sind hier besonders einschlägig, weil `File.tags` die
einzige `@ORM\ManyToMany` im Baum ist.
