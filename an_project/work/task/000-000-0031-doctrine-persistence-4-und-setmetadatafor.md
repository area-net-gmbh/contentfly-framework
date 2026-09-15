---
id: 000-000-0031
title: doctrine/persistence 4 — setMetadataFor() ist dort deprecated
status: review
depends_on: []
---

# doctrine/persistence 4 — `setMetadataFor()` ist dort deprecated

## Context
**Gefunden bei `007-001-0004`, dem Aufteilen der Manifeste.** Das Neuauflösen hob
`doctrine/persistence` von 3.4.5 auf 4.2.0 — ungefragt: Das Paket ist transitiv
(`doctrine/orm` zieht es), und bis dahin hielt nur der Lock die Version fest.

Persistence 4.2 markiert `AbstractClassMetadataFactory::setMetadataFor()` als deprecated:

```
Since 4.2, use a custom ClassMetadataFactory implementation if you need to set
metadata manually.
```

`Classes/Events/LoadMetadata` ruft genau diese Methode, in der letzten Zeile:

```php
$em->getMetadataFactory()->setMetadataFor($className, $classMetadata);
```

**Damit wurde das Deprecation-Gate rot** — und weil das Gate blockierend ist, wäre der Sprung
nicht durchgegangen. Er ist deshalb in `lib/contentfly/composer.json` auf `^3.4` gedeckelt, mit
Datum und Grund im `extra.hinweis`. **Ohne diesen Task verfällt der Deckel:** Er ist begründet,
solange die Begründung ein Ticket hat, und wird sonst zu einer Zeile, die niemand mehr einordnen
kann.

## Die naheliegende Vermutung gehört zuerst geprüft

**Der Aufruf ist vermutlich entbehrlich.** Der Listener bekommt vom Event dieselbe
`ClassMetadata`-Instanz, die die Factory gerade aufbaut und anschliessend selbst ablegt — er
verändert sie in place (`ClassMetadataBuilder::addIndex()`). Das Zurückschreiben wäre dann eine
Wiederholung dessen, was ohnehin passiert.

Trifft das zu, ist der ganze Sprung eine gelöschte Zeile und keine eigene Factory. **Das ist zu
messen, nicht zu glauben:** Der Index muss danach nachweislich noch an jeder Tabelle hängen, die
ihn heute trägt — 15 Stück, gezählt mit `000-000-0028`.

Trifft es nicht zu, verlangt Persistence 4 eine eigene `ClassMetadataFactory`, und dann ist die
Frage, ob der Listener überhaupt der richtige Ort für den Index bleibt.

## Acceptance criteria
- [x] Es ist gemessen, ob `setMetadataFor()` überhaupt etwas bewirkt — nicht geschlossen, sondern an einer frischen Installation gezählt.
- [x] Der Deckel `doctrine/persistence: ^3.4` ist gefallen, oder er steht mit einer neuen Begründung da, die nicht „noch nicht angefasst" lautet.
- [x] `an_project/docs/breaking-changes.md` sagt, was ein Bestandsprojekt davon merkt — auch wenn die Antwort „nichts" ist.
- [x] Die drei Gates bleiben grün: 0 Deprecations bei 0 Ausnahmen, PHPStan `[OK] No errors`, `composer audit --locked` ohne Advisories.
- [x] Die volle Suite bleibt grün, und der `modified_index` hängt nachweislich an denselben Tabellen wie vorher.

## Verification
`composer update doctrine/persistence` ohne Deckel, dann PHPStan und das Deprecation-Gate.
Danach `appcms:install` und die Indizes zählen — vorher gegen nachher, so wie in `000-000-0028`.
Volle Suite.

## Ergebnis

**Die Vermutung trifft zu, und sie ist gemessen:** `setMetadataFor()` bewirkte nichts. Der
Listener bekommt vom Event genau die `ClassMetadata`-Instanz, die die Factory aufbaut und danach
selbst ablegt; `ClassMetadataBuilder::addIndex()` ändert sie in place. Der ganze Sprung ist damit
eine gelöschte Zeile, keine eigene `ClassMetadataFactory`.

**Die Messung, vorher gegen nachher, je an einer frischen `appcms:install`:**

| Stand | Tabellen mit `modified_index` | Schema-Dump |
|---|---|---|
| master (Persistence 3.4.5, mit Aufruf) | 15 | Referenz |
| ohne Aufruf, Persistence 3.4.5 | 15, dieselben | byte-gleich |
| ohne Aufruf, Persistence 4.2.0 | 15, dieselben | byte-gleich |

`orm:validate-schema`: Datenbank in sync. Der Mapping-Teil meldet weiter nur den bekannten Fehler
aus `000-000-0025`.

**Der Deckel ist gefallen.** `lib/contentfly/composer.json` erlaubt `^3.4 || ^4`, der Lock steht
auf 4.2.0. Die Zeile bleibt im Manifest, obwohl `doctrine/orm` das Paket ohnehin zieht: Das
Framework benutzt `Doctrine\Persistence\Mapping\Driver\MappingDriverChain` direkt. Beide Majors
sind erlaubt, weil beide gelaufen sind. Der Hinweis im `extra` des Manifests ist neu geschrieben.

**Damit er nicht zurückkommt:** `LoadMetadataTest` erwartet jetzt bei jedem Fall, dass
`setMetadataFor()` **nie** gerufen wird. Gegenprobe mit dem Listener von master: rot
(„was not expected to be called"). Die dadurch verwaisten Variablen `$em` und `$className` sind
entfernt; im Listener steht, warum nichts zurückgeschrieben wird.

**Die drei Gates:**

- Deprecation-Gate: 0 Zeilen bei 0 Ausnahmen.
- PHPStan: `[OK] No errors`.
- `composer audit --locked`: 0 Advisories, keine Ausnahmen. Lokal im Image `composer:2`
  (Composer 2.10.2) gefahren, weil das lokale Composer 2.6.6 für den Schalter `--abandoned` zu
  alt ist. `tools/ci/audit.sh` bricht dort mit genau dieser Meldung ab.

**Volle Suite:** `Tests: 528, Assertions: 1701, Skipped: 3`. Der erste volle Lauf war rot, und
zwar an `MigrationGuideTest`: Der neue Eintrag in `breaking-changes.md` machte 101 Einträge, der
Kopf von `migration.md` sagte 100. Die Zahl ist nachgezogen.

**Am Rand gesehen, nicht angefasst:** `Classes/Api.php` importiert
`Doctrine\Common\Persistence\Mapping\MappingException`, einen Namensraum, den es seit
Persistence 2.0 nicht mehr gibt. Ein `use` allein lädt nichts, deshalb fällt es nicht auf.
