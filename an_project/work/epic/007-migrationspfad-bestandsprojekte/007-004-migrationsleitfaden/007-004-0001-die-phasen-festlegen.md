---
id: 007-004-0001
title: Die Phasen festlegen und das Register zuordnen
status: todo
depends_on: []
---

# Die Phasen festlegen und das Register zuordnen

## Context
**Die Reihenfolge ist die eigentliche Leistung des Leitfadens, nicht der Text.** Deshalb steht
sie in einem eigenen Task: Entsteht sie beim Schreiben nebenbei, entsteht sie aus der Ordnung
des Registers — und die ist genau die falsche.

`an_project/docs/breaking-changes.md` hat **100 Einträge in 14 Abschnitten** (Stand
2026-09-11, gezählt). Sie sind nach Epic und Story geordnet, also danach, **wann wir etwas
geändert haben.** Ein Projekt fragt anders herum.

## Die Frage, die die Reihenfolge beantworten muss

„Was muss ich an meinen Entities tun, was an meiner Konfiguration, was an meinen Controllern —
und in welcher Reihenfolge, **damit ich zwischendurch nicht auf einem Stand stehe, der gar nicht
läuft**?"

Der letzte Halbsatz ist die Bedingung, an der sich die Reihenfolge entscheidet. Beispiele, die
sie erzwingen:

- Der Rector-Lauf über `Entity/` braucht das Paket, weil die Regel damit kommt — **also erst
  beziehen, dann migrieren.**
- Die `custom/config.php` verliert Felder, deren Wegfall die Anwendung erst beim Start meldet —
  **also vor dem ersten Start.**
- Der Re-Encrypt-Lauf braucht eine laufende Anwendung mit funktionierendem Schema — **also
  zuletzt.**

## Was dieser Task liefert

**Die Phasen, und je Phase die Register-Abschnitte, die dazugehören.** Alle 14 Abschnitte
bekommen eine Phase; keiner bleibt ohne. Entschieden am 2026-09-11: Die Zuordnung ist
**abschnittsweise**, nicht eintragsweise — 14 Zuordnungen statt 100 Markierungen.

**Der Preis ist benannt:** Ein neuer Eintrag in einem bereits zugeordneten Abschnitt gilt damit
automatisch als abgedeckt. Das ist vertretbar, solange das Register die Einzelheiten trägt und
der Leitfaden den Weg — aber es ist eine Entscheidung und keine Selbstverständlichkeit.

**Kein Prosatext.** Der Leitfaden selbst ist `007-004-0002`; dieser Task legt nur fest, in
welcher Reihenfolge er erzählt.

## Acceptance criteria
- [ ] Die Phasen stehen fest, mit je einem Satz, was in ihr passiert und warum sie dort steht.
- [ ] Jeder der 14 Register-Abschnitte ist genau einer Phase zugeordnet; keiner bleibt offen.
- [ ] Wo eine Reihenfolge **erzwungen** ist, steht der Grund dabei — nicht nur die Reihenfolge.
- [ ] Die Entscheidung „abschnittsweise statt eintragsweise" steht mit ihrem Preis.
- [ ] Die Zuordnung liegt an der Stelle, an der `007-004-0002` sie übernehmen kann, ohne sie neu zu erfinden.

## Verification
Ein Leser kann zu jedem der 14 Abschnitte sagen, in welcher Phase er abgearbeitet wird — und für
mindestens drei Phasen, warum sie nicht früher oder später stehen kann.
