---
id: 007-002-0001
title: Der Prüfstein — Beispiel-Entities im Altstand und der Lauf-Rahmen
status: todo
depends_on: []
---

# Der Prüfstein — Beispiel-Entities im Altstand und der Lauf-Rahmen

## Context
**Ohne diesen Task ist kein folgender verifizierbar.** Eine Rector-Regel lässt sich nicht daran
messen, dass ihr Lauf durchgeht — nur daran, was danach im Code steht.

**Hier ist nichts zu borgen.** Nachgemessen am 2026-09-11: Die Entities des Frameworks tragen
durchgehend Attribute — 20 von 22 mit `#[ORM\…]`, die beiden anderen (`BaseUID`,
`Serializable`) ohne Mapping. Der Altstand, gegen den die Regel laufen soll, existiert im Repo
also nicht mehr. Er muss gebaut werden.

**Was der Prüfstein enthalten muss,** damit die folgenden Tasks etwas zu prüfen haben. Die Liste
steht vollständig in `an_project/docs/pim-annotationen-migration.md`:

| | Anzahl | Beispiel |
|---|---|---|
| entfallene `@PIM\*`-Annotationen | 7 | `@PIM\Rte`, `@PIM\Password`, `@PIM\MatrixChooser` |
| entfallene Felder von `@PIM\Config` | 14 | |
| reduzierte Annotationen | 2 | `@PIM\Checkbox` verliert `horizontalAlignment` und `columns`; `@PIM\Radio` zusätzlich `select` |
| gebliebene Annotationen | 5 | `@PIM\Config`, `@PIM\Select`, `@PIM\Virtualjoin`, `@PIM\Permissions`, `@PIM\I18nPermissions` |
| `@ORM\*`-Annotationen | alle benutzten Formen | `@ORM\Column`, `@ORM\ManyToOne`, `@ORM\JoinColumn`, … |

**Jede Zeile mindestens einmal, und die gebliebenen ausdrücklich mit.** Eine Regel, die nur an
dem gemessen wird, was sie ändern soll, kann alles andere mit abräumen, ohne dass es auffällt.

## Der Lauf-Rahmen

`rector/rector` liegt in 1.2.10 im Baum, eine `rector.php` gibt es nicht. Dieser Task legt sie
an — leer, aber lauffähig, mit dem Pfad, den ein Bestandsprojekt angibt, und `--dry-run` als
dokumentierter Normalfall.

**Die Abnahme liegt in der Suite, nicht in einem Wegwerf-Verzeichnis** (entschieden am
2026-09-11): Ein Test fährt Rector gegen den Prüfstein und vergleicht mit dem Sollzustand. Eine
Regel ohne Test verfällt wie eine Anleitung, der niemand folgt — dasselbe Argument, aus dem
`000-000-0029` einen Test bekam.

## Acceptance criteria
- [ ] Der Prüfstein trägt jede der 7 entfallenen Annotationen, jedes der 14 entfallenen `Config`-Felder, die reduzierten `Checkbox`/`Radio`-Felder und die 5 gebliebenen Annotationen — je mindestens einmal.
- [ ] Er trägt `@ORM\*`-Annotationen in den Formen, die der Baum vor Epic `010` benutzte.
- [ ] Ein Sollzustand liegt daneben; er ist von Hand geschrieben und begründet, nicht aus einem Rector-Lauf erzeugt — sonst prüfte der Vergleich die Regel gegen sich selbst.
- [ ] `rector.php` existiert und läuft gegen den Prüfstein durch, ohne etwas zu ändern (noch keine Regel eingetragen).
- [ ] Ein Test fährt den Lauf und vergleicht; er ist grün, solange nichts konfiguriert ist, weil dann Ist gleich Alt ist.
- [ ] Die volle Suite bleibt grün.

## Verification
`./vendor/bin/rector process --dry-run --config=rector.php` gegen den Prüfstein: kein Vorschlag,
weil keine Regel eingetragen ist. Der Test läuft mit. Volle Suite.
