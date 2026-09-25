---
id: 000-000-0099
title: git.md korrigieren — der Merge-Button beachtet union nicht, Update branch schon
status: review
depends_on: []
---

# git.md korrigieren — der Merge-Button beachtet union nicht, Update branch schon

## Context
**Aus `000-000-0097`.** Mit dem Abschluss von `0089` steht in `an_project/docs/git.md`: *„Ein
CHANGELOG-Konflikt sperrt einen Pull Request also nicht mehr."* **Das ist falsch.** Belegt war nur,
dass *Update branch* das Attribut `merge=union` beachtet — daraus wurde zu viel geschlossen.

Nachgerechnet am 2026-09-25, jeweils ohne das Attribut in einem Wegwerf-Klon:

| Merge | ohne `union` | auf GitHub |
|---|---|---|
| *Update branch* auf #61 (`d7ac08e7`) und #62 (`89479f07`) | Konflikt im `CHANGELOG.md` | durchgelaufen, Committer `GitHub` |
| #65 gegen `master` (`5d9e0d41`) | Konflikt im `CHANGELOG.md` | **Merge-Button gesperrt** (`mergeable_state: dirty`), keine CI |

**Die Konfliktprüfung, die den Merge-Button freigibt, beachtet `union` nicht.** Solange sie einen
Konflikt meldet, läuft auch die CI nicht an. *Update branch* — oder ein lokaler Merge von `master` —
löst ihn auf, und danach ist der PR mergebar.

## Acceptance criteria
- [x] `git.md`, *Konflikte in der Buchführung*: Der Absatz sagt, was belegt ist — *Update branch* und lokale Merges beachten `union`, die Konfliktprüfung des PR nicht — mit den Messungen oben.
- [x] `git.md` sagt, was zu tun ist, wenn ein PR nur im `CHANGELOG.md` einen Konflikt meldet: *Update branch* drücken (oder `master` lokal mergen), nicht im Web-Editor auflösen.
- [x] Das Ergebnis von `0089` trägt einen Hinweis auf die Korrektur.

## Verification
Beim nächsten PR, der nur im `CHANGELOG.md` mit `master` kollidiert: Der Merge-Button ist gesperrt,
*Update branch* läuft ohne Konflikt durch, danach startet die CI.

## Ergebnis (2026-09-25)
**`git.md` sagt jetzt, was belegt ist, und was zu tun ist.**

- *Konflikte in der Buchführung:* Tabelle mit den vier nachgerechneten Merges — *Update branch* auf #59,
  #61, #62 beachtet `union`, die Konfliktprüfung von #65 nicht. Dazu, dass die CI nicht startet, solange
  ein Konflikt gemeldet ist, und dass der alte Satz geschlossen statt gemessen war.
- *Was zu tun ist:* bei einem reinen CHANGELOG-Konflikt *Update branch* drücken oder `master` lokal
  mergen — nicht im Web-Editor auflösen. Genau so ist #65 freigekommen.
- `0089` trägt einen Korrekturvermerk; sein Ergebnis bleibt stehen, wie es geschrieben wurde.

Die *Verification* — der nächste PR mit einem reinen CHANGELOG-Konflikt — bleibt eine Beobachtung für
später; die Kriterien betreffen die Doku und sind erfüllt.
