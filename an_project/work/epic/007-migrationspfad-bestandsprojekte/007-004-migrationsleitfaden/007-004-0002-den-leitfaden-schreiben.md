---
id: 007-004-0002
title: Den Leitfaden schreiben
status: review
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
- [x] Der Leitfaden führt in den Phasen aus `007-004-0001` von einem alten Stand auf den neuen.
- [x] Je Phase steht, was zu tun ist, **und woran man merkt, dass sie fertig ist**.
- [x] Was anderswo vollständig steht, wird verwiesen und nicht wiederholt.
- [x] Die gestrichene Oberfläche hat einen eigenen, deutlichen Platz — mit dem, was an ihre Stelle tritt, und dem, was nicht.
- [x] Ein Leser, der das alte Contentfly kennt, kommt ohne Rückfrage durch.
- [x] Die volle Suite bleibt grün.

## Verification
Den Leitfaden von oben nach unten gegen die Vorlage `custom/` durchspielen, soweit das ohne ein
echtes Bestandsprojekt geht. Was dabei hakt, steht im Ergebnis — die Probe am echten Fall ist
`007-005`.

## Ergebnis

**`an_project/docs/migration.md` führt in neun Phasen von einem alten Stand auf den neuen.**
Jede Phase sagt, was zu tun ist, **und woran man merkt, dass sie fertig ist** — und jedes dieser
Abbruchkriterien ist gegen die Vorlage durchgespielt worden:

| Phase | „Fertig, wenn" | Gemessen |
|---|---|---|
| 1 | Erweiterungen da, Sicherung liegt | PHP 8.3.26, `sodium` und `openssl` vorhanden |
| 2 | `vendor/areanet/contentfly` existiert | ja |
| 3 | Trockenlauf meldet `Rector is done!` | ja, gegen `custom/Entity` |
| 4 | `appcms:install` läuft durch | ja |
| 5 | `/api/config` antwortet | HTTP 200 |
| 9 | Trockenlauf meldet nichts zu tun | `Kein Feld mit encoded: true …` |

**Die Meldung aus Phase 9 steht jetzt wörtlich im Leitfaden.** Sie war eine Vermutung
(„meldet folgerichtig, dass es nichts zu tun gibt") und ist jetzt ein Zitat — ein Projekt ohne
verschlüsselte Felder erkennt daran, dass es richtig liegt und nicht etwas übersehen hat.

## Die gestrichene Oberfläche steht vor allem anderen

**Nicht in einer Phase, sondern davor.** Ein Projekt, das die Admin-UI benutzt, verliert sie
ersatzlos — und das ist die Entscheidung, die vor der ersten Zeile Arbeit steht, nicht eine
Stolperstelle zwischen zwei Befehlen. Der Abschnitt sagt, was an ihre Stelle tritt (API und
Console) und was nicht (eine fertige Oberfläche), und schliesst mit dem Satz, der die Sache
ehrlich macht: **Wer diese Entscheidung nicht treffen will, migriert nicht.**

## Verwiesen, nicht wiederholt

Vier Dinge stehen anderswo vollständig, und der Leitfaden zeigt darauf:

| Was | Wo |
|---|---|
| Bezugsweg, fünf gemessene Schritte | `breaking-changes.md`, *Paketgrenze* |
| Der Rector-Lauf samt Grenzen und der Zwei-Lauf-Regel | `pim-annotationen-migration.md`, Abschnitt 7 |
| Die zugesicherten `$app[...]`-Schlüssel | `dev-guide.md` |
| Der Re-Encrypt-Ablauf | `deployment.md` |

**Das ist die Bauregel, und sie war in diesem Epic schon dreimal die Begründung:** Zwei
Beschreibungen desselben Laufs laufen auseinander.

## Zwei Korrekturen beim Nachprüfen der eigenen Verweise

**1. Eine Zahl stimmte nicht.** Ich hatte geschrieben, die acht `FRONTEND_*`-Felder stünden in
`pim-annotationen-migration.md`, Abschnitt 6. Dort stehen **zwei** — die acht sind ein eigener
Eintrag im Register unter *Konfiguration*, und Abschnitt 6 führt zusätzlich zwei weitere samt
ihrer abgeleiteten Konstanten. **Wer nur eine der beiden Listen abarbeitet, lässt Zeilen
stehen**, und genau das steht jetzt dort.

**2. Der Leitfaden war nirgends verzeichnet.** `CLAUDE.md` listet die Projektdokumente; die neue
Datei fehlte. Ein Leitfaden, den niemand findet, ist keiner — er steht jetzt an erster Stelle
der Liste, mit dem Hinweis, dass `breaking-changes.md` das Register dazu ist.

**Zahlen:** Volle Suite `OK (521 tests, 1673 assertions)`, 0 Deprecations bei 0 Ausnahmen,
0 Byte Postausgang. PHPStan `[OK] No errors`. Der Leitfaden hat 255 Zeilen.

**Was offen bleibt, und zwar absichtlich:** Ob ein Leser wirklich ohne Rückfrage durchkommt,
zeigt erst `007-005` an einem echten Bestandsprojekt. Die Vorlage ist der Zielzustand — sie kann
belegen, dass die Abbruchkriterien stimmen, aber nicht, dass der Weg dorthin vollständig
beschrieben ist.
