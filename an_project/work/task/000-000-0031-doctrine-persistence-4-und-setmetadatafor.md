---
id: 000-000-0031
title: doctrine/persistence 4 — setMetadataFor() ist dort deprecated
status: todo
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
- [ ] Es ist gemessen, ob `setMetadataFor()` überhaupt etwas bewirkt — nicht geschlossen, sondern an einer frischen Installation gezählt.
- [ ] Der Deckel `doctrine/persistence: ^3.4` ist gefallen, oder er steht mit einer neuen Begründung da, die nicht „noch nicht angefasst" lautet.
- [ ] `an_project/docs/breaking-changes.md` sagt, was ein Bestandsprojekt davon merkt — auch wenn die Antwort „nichts" ist.
- [ ] Die drei Gates bleiben grün: 0 Deprecations bei 0 Ausnahmen, PHPStan `[OK] No errors`, `composer audit --locked` ohne Advisories.
- [ ] Die volle Suite bleibt grün, und der `modified_index` hängt nachweislich an denselben Tabellen wie vorher.

## Verification
`composer update doctrine/persistence` ohne Deckel, dann PHPStan und das Deprecation-Gate.
Danach `appcms:install` und die Indizes zählen — vorher gegen nachher, so wie in `000-000-0028`.
Volle Suite.
