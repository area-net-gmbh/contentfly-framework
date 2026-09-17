---
id: 000-000-0051
title: master schützen und den Pull Request zur Regel machen
status: todo
depends_on: [011-002-0002]
---

# master schützen und den Pull Request zur Regel machen

## Context
Seit `011-002-0001` liegt das Repository auf GitHub, seit `011-002-0002` läuft die Pipeline dort.
Damit gibt es zum ersten Mal Pull Requests — **aber sie sind bisher eine Konvention, keine Regel**:
`master` ist ungeschützt, und ein `git push origin master` schreibt daran vorbei. Auch meiner.

Solange das so ist, hängt die Zusicherung der Pipeline an der Disziplin dessen, der gerade pusht.
Das ist genau die Konstruktion, gegen die dieses Projekt seine drei Gates gebaut hat: Eine Prüfung,
die man vergessen kann, ist eine Prüfung, die irgendwann vergessen wird.

**Der Zeitpunkt ist jetzt richtig, nicht früher:** Eine Schutzregel, die grüne Läufe verlangt, bevor
es grüne Läufe gibt, sperrt das Repository aus. Seit dem 2026-09-17 liegt der erste vor.

## Zu entscheiden
- **Welche Checks verlangt werden.** Alle fünf, oder nur die drei ohne Umgebung? Die Testjobs
  brauchen zwei Minuten; die drei Prüfungen unter einer.
- **Ob eine Freigabe nötig ist.** In einem Team von wenigen ist „mindestens eine Freigabe" schnell
  ein Hindernis ohne Nutzen — in einem, das wächst, das Gegenteil.
- **Ob die Regel für Administratoren gilt.** „Include administrators" ist die Frage, ob die Regel
  eine Regel ist oder eine Empfehlung. Ohne sie greift sie für niemanden, der sie umgehen könnte.
- **Was mit `git push --force` auf `master` geschieht** — die Schutzregel verbietet es standardmässig,
  und das sollte sie auch.

## Acceptance criteria
- [ ] `master` ist gegen direkten Push geschützt; Änderungen gehen über einen Pull Request.
- [ ] Die Pipeline ist als erforderlicher Check eingetragen, mit der entschiedenen Auswahl.
- [ ] Die Entscheidungen oben sind in `an_project/docs/git.md` festgehalten — es ist die Datei, die
      die Framework-Baseline für dieses Projekt verengt, und der Abschnitt *Integration branch* ist
      heute leer.
- [ ] `deployment.md` nennt die Regel bei den Voraussetzungen.
- [ ] **Gegenprobe:** Ein direkter Push auf `master` wird abgelehnt, und die Meldung sagt warum.

## Verification
`git push origin master` mit einem belanglosen Commit auf einem Wegwerf-Branch, der auf `master`
zeigt — GitHub lehnt ab. Danach dieselbe Änderung über einen Pull Request: Sie geht durch, sobald
die Checks grün sind, und **nicht** vorher.

## Hinweis zum Zusammenspiel mit dem Framework
`.an_framework/commands/done.md` beschreibt zwei Wege und nennt den Unterschied ausdrücklich:

> „A team that integrates through a protected branch / Merge Request does **not** run `/done`; it
> pushes the branch and opens the MR instead."

Mit dieser Regel wechselt das Projekt endgültig auf den zweiten Weg. Die **Buchführung** von `/done`
— Status auf `done`, Häkchen im Elternteil, Changelog-Zeile — bleibt trotzdem nötig; sie wird dann
zum letzten Commit auf dem Branch, bevor der Pull Request gemergt wird. Das gehört in
`an_project/docs/git.md`, sonst macht es jeder anders.
