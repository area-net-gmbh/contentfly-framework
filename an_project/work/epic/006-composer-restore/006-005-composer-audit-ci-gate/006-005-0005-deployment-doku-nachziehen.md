---
id: 006-005-0005
title: deployment.md beschreibt die Gates und den Umgang mit einem Fund
status: todo
depends_on: [006-005-0004]
---

# deployment.md beschreibt die Gates und den Umgang mit einem Fund

## Context
Ein Gate ist erst dann eines, wenn klar ist, was zu tun ist, wenn es anschlägt. Sonst geschieht
das Naheliegende: Der nächste, dem die Pipeline rot ins Haus fällt, setzt die Kennung auf die
Ausnahmeliste, und das Gate ist weg.

Die Story verlangt es ausdrücklich: *„Die Pipeline-Definition liegt im Repo, und
`an_project/docs/deployment.md` beschreibt, was sie prüft und wie man einen Fund behandelt."*

## Umfang

### Was in `deployment.md` gehört
Der Abschnitt über die Pipeline sagt heute, `composer audit --locked` und das
„0 Deprecations"-Gate „kommen mit `006-005` in dieselbe Pipeline". Ab hier sind sie da und
beschreiben sich selbst:

| Gate | prüft | blockiert |
|---|---|---|
| `composer audit --locked` | den Lock gegen die Advisory-Datenbank | ja, mit namentlicher Ausnahmeliste |
| abgelaufene Ausnahmen | ob jede Ausnahme noch greift | ja |
| Deprecations (Laufzeit) | das Serverlog nach dem Testlauf | auf 8.3 ja, auf 8.4 melden |
| PHPStan | deprecated APIs ohne Ausführung | nein, `allow_failure` |

### Der Ablauf bei einem Fund — der eigentliche Inhalt
Nicht „was tun", sondern **in welcher Reihenfolge gefragt wird**:

1. Gibt es ein Release, das die Meldung behebt? Dann Constraint anheben — und die
   Constraint-Kette aus `006-001-0003` gegenrechnen, bevor irgendetwas committet wird.
2. Kein Release, aber ein Weg um die Nutzung herum? Dann ist es ein Code-Ticket, kein
   Manifest-Ticket.
3. Weder noch → Ausnahme, **einzeln nach CVE-Kennung**, mit Begründung und dem Epic, das sie
   auflöst. Nie paketweise.

Und die Gegenregel, ohne die der Rest nichts wert ist: **Eine Ausnahme ohne benannten Auflöser
ist keine Ausnahme, sondern ein abgeschaltetes Gate.**

### Warum die fünf heutigen Ausnahmen dort erklärt gehören
Sie sind der Präzedenzfall, an dem der nächste sich orientiert. Wer nur die Liste sieht, hält
Ausnehmen für den Normalweg. Wer daneben liest, dass alle fünf zur selben unauflösbaren
Constraint-Kette gehören und mit **einem** Epic verschwinden, versteht die Regel.

### Was sonst noch nachzuziehen ist
- `tests/README.md`, Abschnitt *In der Pipeline* — er listet die Schritte des Nachspielens; die
  neuen Jobs gehören dazu.
- Der Satz in `deployment.md`, dass die Gates „mit `006-005`" kämen, ist danach falsch.

## Abgrenzung
Keine Änderung an den Gates selbst; die stehen nach `006-005-0001` bis `-0004` fest. Kein
Nachtragen in `tech-stack.md` — die Forderung dort bleibt, wie sie ist, sie ist ja jetzt erfüllt.

## Acceptance criteria
- [ ] `deployment.md` beschreibt alle vier Prüfungen: was sie prüfen und ob sie blockieren.
- [ ] Der Ablauf bei einem Fund steht als **Reihenfolge von Fragen** da, nicht als Aufzählung
      von Möglichkeiten.
- [ ] Die Regel „einzeln nach Kennung, nie paketweise, nie ohne benannten Auflöser" steht dort.
- [ ] Die fünf heutigen Ausnahmen sind als Präzedenzfall erklärt, mit dem Epic, das sie auflöst.
- [ ] `tests/README.md` nennt die neuen Schritte im Abschnitt über das Nachspielen.
- [ ] Kein Dokument sagt mehr, die Gates kämen erst noch.

## Verification
Die beschriebenen Befehle werden **ausgeführt**, nicht nur gelesen — dieselbe Regel wie in
`008-005-0004` und `006-003-0003`. Konkret: Der Abschnitt *In der Pipeline* aus
`tests/README.md` wird Schritt für Schritt in Docker nachgespielt, einschliesslich der neuen
Jobs, und muss zum beschriebenen Ergebnis führen.

Dazu die Probe aufs Exempel für den Fund-Ablauf: Eine der fünf Ausnahmen kurzzeitig entfernen,
dem beschriebenen Ablauf folgen und prüfen, ob er zu der Entscheidung führt, die tatsächlich
getroffen wurde. Führt er woandershin, ist der Text falsch, nicht die Entscheidung.
