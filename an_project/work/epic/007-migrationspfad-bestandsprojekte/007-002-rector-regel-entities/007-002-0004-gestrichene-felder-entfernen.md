---
id: 007-002-0004
title: Die gestrichenen Felder aus gebliebenen Annotationen entfernen
status: todo
depends_on: [007-002-0002, 007-002-0003]
---

# Die gestrichenen Felder aus gebliebenen Annotationen entfernen

## Context
**Das ist der eigene Anteil dieser Story — und er ist schmaler und anders gelegen, als die Story
annahm.** Für das Entfernen einer ganzen Annotation gibt es eine fertige Regel (siehe
`007-002-0003`). Für das Entfernen eines **Feldes aus einer Annotation, die bleibt**, gibt es
keine. Nachgesehen am 2026-09-11 über alle konfigurierbaren Rector-Regeln.

**Betroffen sind drei Annotationen, die bleiben:**

| Annotation | entfallene Felder |
|---|---|
| `@PIM\Config` | 14, Liste in `pim-annotationen-migration.md` Abschnitt 3 |
| `@PIM\Checkbox` | `horizontalAlignment`, `columns` |
| `@PIM\Radio` | `horizontalAlignment`, `columns`, `select` |

**Der Unterschied zu `007-002-0003` ist der Grund für den eigenen Task:** Dort wird eine Zeile
gelöscht. Hier muss aus `@PIM\Config(hidden=true, label="x", readonly=false)` genau
`label="x"` übrig bleiben — mit korrekter Syntax, auch wenn das erste oder letzte Feld fällt,
auch bei mehrzeiligen Annotationen, auch wenn danach kein Feld mehr übrig ist und die Annotation
ohne Klammern dastehen muss.

**Die Fälle, an denen so etwas scheitert, gehören in den Prüfstein:** ein einziges Feld, das
fällt · das erste von mehreren · das letzte von mehreren · eine mehrzeilige Annotation · eine, in
der ein entfallenes und ein bleibendes Feld nebeneinander stehen.

**Wenn eine eigene Rector-Regel dafür zu teuer ist, ist das ein zulässiges Ergebnis** — dann
steht es als Grenze da und der Leitfaden sagt, was von Hand zu tun bleibt. Eine Automatik, die
Annotationen halb kaputt zurücklässt, wäre schlimmer als keine.

## Acceptance criteria
- [ ] Die 14 `Config`-Felder und die fünf `Checkbox`/`Radio`-Felder werden entfernt — oder es steht begründet da, dass und warum nicht.
- [ ] Die fünf genannten Grenzfälle sind im Prüfstein und einzeln geprüft.
- [ ] Eine Annotation, aus der das letzte Feld fällt, bleibt syntaktisch gültig.
- [ ] Kein bleibendes Feld verschwindet — geprüft an den 10 Feldern, die `@PIM\Config` behält.
- [ ] Die volle Suite bleibt grün.

## Verification
Rector gegen den Prüfstein, Ergebnis gegen den Sollzustand, Fall für Fall. Danach muss der
Prüfstein von Doctrine **einlesbar** sein — eine syntaktisch gültige Datei mit einer kaputten
Annotation fällt beim Vergleich nicht auf, beim `AnnotationReader` schon.
