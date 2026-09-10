---
id: 010-001-0003
title: Die 21 Framework-Entities auf Attribute umstellen
status: done
depends_on: [010-001-0002]
---

# Die 21 Framework-Entities auf Attribute umstellen

## Context
Der eigentliche Schnitt: 166 `@ORM\*` und 36 `@PIM\*` werden zu Attributen, und der Treiber für
den Namensraum `Areanet\PIM\Entity` wechselt von `newDefaultAnnotationDriver()` auf
`AttributeDriver`.

**Warum das in einem Zug gehen muss:** Doctrine liest je Namensraum mit **einem** Treiber.
Ein halb umgestellter Namensraum hat kein Verhalten, das man messen könnte.

> **Umfangskorrektur, 2026-09-09 — gemessen, nicht geschätzt.** Hier stand, die Aufteilung nach
> Namensräumen sei der Grund, warum die Vorlage einen eigenen Task bekommt. **Das trägt nicht.**
> `Custom\Entity\Core\Example` erbt von `Areanet\PIM\Entity\Base`, und bei einer
> `MappedSuperclass` setzt Doctrine an den geerbten Feldern **kein** `inherited`
> (`ClassMetadataFactory::addMappingInheritanceInformation()`). Der Treiber der **Unterklasse**
> liest sie deshalb neu — ein Annotation-Treiber findet an einer umgestellten Oberklasse nichts
> und meldet `No identifier/primary key specified for Entity "Custom\Entity\Core\Example"`.
> Isoliert reproduziert, ohne Datenbank.
>
> **`custom/Entity/Core/Example.php` und `custom/Traits/User.php` sind deshalb Teil dieses
> Tasks.** `010-001-0004` behält die Kommentare der Vorlage und den Plugin-Pfad. Das ist eine
> Korrektur einer falschen Annahme, keine Erweiterung des Umfangs — dieselbe Art Richtigstellung
> wie in `009-005-0001`.

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
- [x] Alle 21 Entities unter `lib/contentfly/Entity/` tragen Attribute statt Docblock-Annotationen; kein `@ORM\` und kein `@PIM\` bleibt dort als Metadatum stehen (in Erklärungen dürfen die Namen weiter vorkommen).
- [x] Der Treiber für `Areanet\PIM\Entity` ist ein `AttributeDriver`; die Chain aus `EntityManagerFactory` bleibt im Aufbau unverändert.
- [x] Der Auswertungszeitpunkt der Konstanten ist **gemessen** belegt, nicht angenommen.
- [x] `POST /api/config` liefert dasselbe Schema wie vorher — Feld für Feld verglichen, nicht überflogen.
- [x] Die Suite ist grün, ohne eine einzige geänderte Zusicherung. Eine nötige Anpassung ist ein Befund und braucht eine Begründung.

## Verification
Schema-Vergleich: `POST /api/config` vor und nach der Umstellung als JSON sichern und
`diff`en — erwartet wird kein Unterschied. Dazu `orm:validate-schema` und die volle Suite. Die
neun ManyToMany-Tests aus `006-002-0001` sind hier besonders einschlägig, weil `File.tags` die
einzige `@ORM\ManyToMany` im Baum ist.

## Ergebnis

**Das Schema ist Feld für Feld identisch, `_hash` eingeschlossen** — 13 Entities, 189
Properties, `c5e3276bc121c309c7bfe713bda2196a` vorher wie nachher. Das war die Abnahme: eine
Formatänderung, keine Verhaltensänderung.

202 Annotationen sind zu Attributen geworden, und `newDefaultAnnotationDriver()` ist aus der
`EntityManagerFactory` verschwunden.

### Der Befund, der den Task-Schnitt widerlegt hat

Der Plan war, nach Namensräumen zu trennen: `Areanet\PIM\Entity` hier, `Custom\Entity` in
`010-001-0004`. Doctrine liest je Namensraum mit einem Treiber, also schien das zu gehen.

**Es geht nicht, und der Grund liegt eine Ebene tiefer.** Bei einer `MappedSuperclass` setzt
`ClassMetadataFactory::addMappingInheritanceInformation()` an den geerbten Feldern **kein**
`inherited` — nachgelesen im Quelltext, die Bedingung lautet wörtlich
`! isset($mapping['inherited']) && ! $parentClass->isMappedSuperclass`. Damit ist
`isInheritedField()` an der Unterklasse falsch, und **ihr** Treiber liest die Felder der
Oberklasse noch einmal selbst. Ein Annotation-Treiber findet an einer umgestellten Oberklasse
nichts mehr:

```
No identifier/primary key specified for Entity "Custom\Entity\Core\Example"
sub class of "Areanet\PIM\Entity\Base". Every Entity must have an identifier/primary key.
```

Isoliert reproduziert — zwei Treiber in einer Chain, SQLite im Speicher, keine Anwendung
drumherum: `Areanet\PIM\Entity\File` lädt mit 18 Feldern und Id, `Custom\Entity\Core\Example`
scheitert.

**Ein Wahlschalter je Mapping stand kurz im Code und ist wieder entfallen.** Er hätte eine
Freiheit angeboten, die es nicht gibt.

### Drei Dinge, die ohne die Umstellung nicht sichtbar geworden wären

**1. Der Installer baut denselben Mapping-Block ein zweites Mal.**
`InstallCommand::bootDoctrine()` wiederholt die Liste aus `bootstrap.php`, und sein eigener
Kommentar sagt voraus, was dann passiert: „Weicht sie ab, installiert der Installer gegen ein
anderes Schema, als die Anwendung später benutzt." Genau das trat ein, solange der Wahlschalter
existierte. Mit seinem Wegfall ist die Falle zu — aber **die Doppelung bleibt** und gehört als
eigener Task aufgeschrieben.

**2. `custom/Traits/User.php` trug `@ORM\Column` ohne jeden `use`-Import** — und es
funktionierte, weil `ReflectionProperty::getDeclaringClass()` für eine aus einem Trait
übernommene Eigenschaft die **benutzende Klasse** liefert; der `AnnotationReader` löste `@ORM`
also gegen die Imports von `Entity\User` auf. Ein Attribut wird gegen die Imports **seiner
Datei** aufgelöst. Ohne den Import wäre `nameExample` **still** aus dem Schema gefallen — und
genau so kam es, sichtbar allein im Schema-Vergleich. Der Import steht jetzt da, mit der
Erklärung daneben.

**3. `MultijoinType.php:80` griff ungeschützt auf `joinColumns[0]` zu.** Unter Attributen steht
`JoinColumn` neben `JoinTable` statt darin, also ist `JoinTable::$joinColumns` leer und der
Zugriff meldet `Undefined array key 0`. **Das Ergebnis ändert sich nicht** — die alte
`JoinColumn` trug keinen `name`, es lief also schon vorher in denselben Rückfall; neu ist nur
die Warnung. Abgesichert mit `isset()`, wie es die Zeile direkt darunter für
`inverseJoinColumns[0]` seit jeher tut.

### Die drei Punkte, die der Task ausdrücklich zu prüfen verlangte

| Punkt | Ergebnis |
|---|---|
| **Konstanten** | Gemessen, nicht angenommen: Die Datei lädt und das Attribut ist auffindbar **ohne** die Konstante; erst `newInstance()` braucht sie und wirft sonst `Error — Undefined constant`. `bootstrap.php` definiert `APPCMS_ID_TYPE` in Zeile 178, der EntityManager entsteht in Zeile 267. Die Reihenfolge stimmt. |
| **`@ORM\CustomIdGenerator`** | An `Base` und `Log` unverändert übernommen; die Id-Erzeugung aus `009-005-0002` läuft weiter. |
| **Vererbung** | Vier `MappedSuperclass`, zwei `InheritanceType` — und genau hier lag der Befund oben. |
| **Lifecycle-Callbacks** | `#[ORM\HasLifecycleCallbacks]` mit `PrePersist`/`PreUpdate` an `Base`, im Schema-Vergleich bestätigt. |

### Wie umgestellt wurde

Ein eigener Umschreiber, **kein Rector**. Rector 1.0 liegt im Baum, aber ohne das
Doctrine-Set — die Zuordnungen wären ohnehin von Hand zu schreiben gewesen, und Rector druckt
jede berührte Datei neu. Der Diff hätte Formatierung weit ausserhalb der Annotationen enthalten,
und die Abnahme dieses Tasks ist ein Zeilenvergleich.

**Der Umschreiber kann nur einzeilige Annotationen** — bei der mehrzeiligen `@ORM\Table` der
Vorlage hat er den Docblock zerrissen. Aufgefallen beim Durchsehen, Datei zurückgesetzt, von
Hand gemacht. Die 21 Framework-Entities tragen keine mehrzeilige Annotation, dort trug er.

`Index` und `UniqueConstraint` stehen jetzt **neben** `Table` statt darin: Als Annotation
mussten sie mangels Wiederholbarkeit in ein Array, ein Attribut darf sich wiederholen
(`Attribute::IS_REPEATABLE`).

### Regel 3 hat ausgelöst, und das war richtig

Nach der Umstellung meldete PHPStan
`Ignored error pattern … newDefaultAnnotationDriver … was not matched` — die Ausnahme traf
nichts mehr. **Sie ist gestrichen, aber mit einer Notiz:** `Classes/Plugin.php` ruft die Methode
weiterhin, und PHPStan meldet sie dort **nicht**, weil `$this->app['orm.em']` über `ArrayAccess`
`mixed` liefert. Das Muster hat diese Stelle also nie abgedeckt; sein Wegfall verdeckt nichts.
Der Plugin-Pfad wird mit `010-001-0004` entschieden.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Schema aus `$app['schema']`, vorher gegen nachher | **identisch, Feld für Feld, inkl. `_hash`** |
| Volle Suite | `OK (267 tests, 639 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| Postausgang der Versandfalle | 0 Byte |
| `appcms:install` auf frischer Datenbank | läuft durch |
| Verbliebene `@ORM\`/`@PIM\`-Annotationen in Entities | 0 |
