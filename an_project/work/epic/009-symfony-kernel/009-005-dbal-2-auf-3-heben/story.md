---
id: 009-005-0000
title: DBAL 2 auf 3 heben — die Vorbedingung des Kernel-Schnitts
status: todo
depends_on: []
---

# DBAL 2 auf 3 heben — die Vorbedingung des Kernel-Schnitts

## Goal
`doctrine/dbal` steht auf 3.10, die Suite aus Epic `008` ist grün, und **Silex läuft dabei
weiter**. Nach dieser Story ist der Kernel-Schnitt (`009-002`) nicht mehr durch Doctrine
blockiert.

## Warum es diese Story gibt
Aufgefallen beim ersten Versuch von `009-002-0001`, und zwar sofort:

```
symfony/http-foundation[v7.4.0, ..., v7.4.18] conflict with doctrine/dbal <3.6.
```

Das ist ein **harter Konflikt**, kein Constraint, den man umgehen kann. Er ist mit
`symfony/http-foundation` **v7.1.7** dazugekommen — in v7.0.0 gibt es ihn noch nicht. Auf einer
alten Patch-Version stehenzubleiben hiesse, auf Sicherheitsfixes zu verzichten, also genau auf
das, wogegen Epic `009` antritt.

**Damit ist die Abgrenzung des Epics widerlegt.** Sie sagte „Kein Doctrine-Umbau — `009` lässt
Doctrine, wie es ist"; das ist nicht möglich. Der Satz ist im Epic entsprechend richtiggestellt,
nicht stillschweigend übergangen.

## Warum eine eigene Story und nicht ein Task in `009-002`
**Weil sie sich prüfen lässt.** Silex nagelt `symfony/*` auf `^4.0` fest — Doctrine nicht. DBAL 3
lässt sich also installieren, während der alte Kernel weiterläuft, und die **volle Suite läuft
dabei**. Das ist der einzige Teil des Umbaus, für den das gilt.

Eigenständig gemergt heisst: Der riskante Schnitt startet von einem nachweislich guten Stand,
und wenn später etwas klemmt, ist die Doctrine-Änderung einzeln zurücknehmbar. Läge sie im
Schnitt-Merge, wäre sie es nicht.

## Umfang, gemessen
`doctrine/orm` bleibt auf **2.20** — es erlaubt `doctrine/dbal ^2.13.1 || ^3.2` bereits. Die
Annotationen und der Entity-Layer bleiben unberührt; Epic `010` behält seinen Umfang bis auf den
DBAL-Teil.

Die eigene DBAL-Oberfläche ist klein:

| Aufruf | Fundstellen | In DBAL 3 |
|---|---|---|
| `Connection::fetchAll()` | 2 | entfallen → `fetchAllAssociative()` |
| `Connection::fetchAssoc()` | 1 | entfallen → `fetchAssociative()` |
| `Connection::exec()` | 1 | entfallen → `executeStatement()` |
| `QueryBuilder::execute()->fetchAll()` | 2 | `executeQuery()->fetchAllAssociative()` |
| `Connection::executeQuery()->rowCount()` | 3 | bleibt, Rückgabetyp geändert |

Dazu die achtzehn Doctrine-Console-Commands in `bin/console.php`: `ImportCommand` gibt es in
DBAL 3 nicht mehr, und die Helper-Konstruktion hat sich geändert.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 009-005-0001 — DBAL 3.10 installieren und den Bruch sichtbar machen
- [ ] 009-005-0002 — Die entfallenen DBAL-Aufrufe nachziehen
- [ ] 009-005-0003 — Die Doctrine-Console-Commands und der Nachweis
