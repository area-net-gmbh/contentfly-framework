---
id: 007-002-0000
title: Die Rector-Regel für das Entity-Verzeichnis
status: todo
depends_on: []
---

# Die Rector-Regel für das Entity-Verzeichnis

## Goal
Ein Bestandsprojekt lässt **einen Lauf** über sein `Entity/`-Verzeichnis laufen und hat danach
PHP-Attribute statt `@ORM\*`-Annotationen, und keine der mit Epic `012` gestrichenen
`@PIM\*`-Annotationen und `@PIM\Config`-Felder mehr. Das ersetzt hunderte Handgriffe pro Projekt.

## Ausgangslage

Zwei Hälften, und beide sind vorbereitet:

- **`@ORM\*` → Attribute.** `rector/rector` liegt im Baum; eine `rector.php` gibt es nicht.
  Rector bringt die ORM-Umstellung als fertigen Satz mit. Epic `010` hat die Entities des
  Frameworks bereits umgestellt — was dabei nicht glatt lief, ist der Erfahrungswert, auf dem
  diese Story aufsetzt.
- **Die gestrichenen `@PIM\*` raus.** `an_project/docs/pim-annotationen-migration.md` listet sie
  vollständig: 7 entfallene Annotationen, 14 entfallene Felder von `@PIM\Config`, die entfallene
  Plugin-Schnittstelle und die `FRONTEND_*`-Konfiguration. Die Datei nennt sich selbst Grundlage
  dieser Regel. Dafür gibt es keinen fertigen Rector-Satz; das ist der eigene Anteil.

**Warum das nicht optional ist:** Ein stehengebliebenes Feld ist kein geduldetes Relikt. Der
`AnnotationReader` bricht schon beim Einlesen ab, wenn eine Annotation ein Feld trägt, das ihre
Klasse nicht kennt — das Projekt startet dann gar nicht.

## Abnahme

Die Regel läuft gegen einen Satz Beispiel-Entities, der jede der gelisteten Annotationen und
jedes gelistete Feld mindestens einmal enthält, und das Ergebnis wird geprüft — nicht nur, dass
der Lauf durchgeht. Was die Regel **nicht** kann, steht dabei: Eine Automatik, deren Grenzen
niemand kennt, ist gefährlicher als gar keine.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 007-002-0001 — Der Prüfstein — Beispiel-Entities im Altstand und der Lauf-Rahmen
- [ ] 007-002-0002 — ORM-Annotationen zu Attributen
- [ ] 007-002-0003 — Die sieben gestrichenen PIM-Annotationen entfernen
- [ ] 007-002-0004 — Die gestrichenen Felder aus gebliebenen Annotationen entfernen
- [ ] 007-002-0005 — Die Grenzen benennen und den Aufruf dokumentieren

`0001` liefert den Prüfstein, ohne den kein folgender Task verifizierbar ist. `0002` und `0003`
hängen nur daran und sind untereinander unabhängig. `0004` braucht beide — es arbeitet auf dem,
was sie übrig lassen. `0005` sammelt ein.
