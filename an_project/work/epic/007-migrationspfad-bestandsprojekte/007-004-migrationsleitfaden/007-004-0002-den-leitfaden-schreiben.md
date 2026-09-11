---
id: 007-004-0002
title: Den Leitfaden schreiben
status: todo
depends_on: [007-004-0001]
---

# Den Leitfaden schreiben

## Context
**Der Text, den ein Bestandsprojekt tatsächlich liest.** Die Reihenfolge steht aus
`007-004-0001`; dieser Task erzählt sie.

## Was hineingehört — und was nur als Verweis

**Hinein gehört, was ein Projekt TUT.** Befehle, die man kopieren kann; Entscheidungen, die es
treffen muss; Stolperstellen, die es sonst erst im Betrieb merkt.

**Als Verweis gehört hinein, was schon woanders steht — und zwar vollständig woanders:**

| Was | Wo es steht | Warum nicht hier |
|---|---|---|
| Der Bezugsweg von der Kopie auf das Paket | `breaking-changes.md`, *Paketgrenze* | fünf gemessene Schritte, mit `007-001-0005` einmal gegangen |
| Der Rector-Lauf samt Grenzen | `pim-annotationen-migration.md`, Abschnitt 7 | dort steht die Liste, aus der die Regel gebaut ist |
| Die `$app[...]`-Schlüssel | `dev-guide.md` | dort steht die Liste, die ein Test hält |
| Der Re-Encrypt-Lauf | `deployment.md` | Kommando, Trockenlauf und Rückweg, seit `010-004` fertig |

**Zwei Beschreibungen desselben Laufs laufen auseinander.** Das ist in dieser Story schon dreimal
die Begründung gewesen; hier ist es die Bauregel.

## Der grösste Brocken

**Ein Projekt, das die Admin-UI heute benutzt, verliert sie ersatzlos** (Epic `012`). Der
Leitfaden sagt klar, was an ihre Stelle tritt — API und Console — **und was nicht**. Das ist der
Punkt, an dem ein Projekt entscheidet, ob es den Weg überhaupt gehen will, und er gehört nicht
zwischen zwei Befehle geklemmt.

## Acceptance criteria
- [ ] Der Leitfaden führt in den Phasen aus `007-004-0001` von einem alten Stand auf den neuen.
- [ ] Je Phase steht, was zu tun ist, **und woran man merkt, dass sie fertig ist**.
- [ ] Was anderswo vollständig steht, wird verwiesen und nicht wiederholt.
- [ ] Die gestrichene Oberfläche hat einen eigenen, deutlichen Platz — mit dem, was an ihre Stelle tritt, und dem, was nicht.
- [ ] Ein Leser, der das alte Contentfly kennt, kommt ohne Rückfrage durch.
- [ ] Die volle Suite bleibt grün.

## Verification
Den Leitfaden von oben nach unten gegen die Vorlage `custom/` durchspielen, soweit das ohne ein
echtes Bestandsprojekt geht. Was dabei hakt, steht im Ergebnis — die Probe am echten Fall ist
`007-005`.
