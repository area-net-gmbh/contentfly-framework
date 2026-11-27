---
id: 000-000-0107
title: check Bezugsweg — Host-Key von github.com im Job hinterlegen statt darauf zu hoffen
status: todo
depends_on: []
---

# check Bezugsweg — Host-Key von github.com im Job hinterlegen statt darauf zu hoffen

## Context
**Am 2026-09-29 ist `check: Bezugsweg von aussen` einmal rot geworden, ohne dass sich etwas
geändert hatte.** Der Lauf gehörte zu PR #101, der drei Markdown-Dateien anfasst — Buchführung,
kein Code. Die Meldung:

```
Failed to execute git clone --mirror -- git@github.com:area-net-gmbh/contentfly-framework-dist.git
Host key verification failed.
```

Derselbe Commit lief **davor grün** und nach einem `gh run rerun --failed` **wieder grün**. Es ist
also kein Fehler am Paket, an der Version oder am Lock — genau an den Dingen, für die dieses Gate
gebaut wurde.

**Warum das mehr ist als ein Ärgernis.** Das Gate ist seit `000-000-0051` ein **erforderlicher
Check**. Ein erforderlicher Check, der gelegentlich ohne Grund rot wird, erzieht zum Re-Run — und
wer beim vierten Fehlschlag reflexhaft neu startet, startet auch den fünften neu, der echt war.
Dieselbe Überlegung, mit der `000-000-0104` einen flaky Test nicht toleriert hat.

**Die wahrscheinliche Ursache, nicht bewiesen:** Der Job klont per SSH
(`git@github.com:…-dist.git`), und der Host-Key von `github.com` liegt im Container nicht in
`known_hosts`. Ob Composer beim ersten Mal über eine andere Ebene kommt (HTTPS-Fallback,
gecachter VCS-Spiegel) und nur im Grenzfall auf SSH fällt, gehört zur Untersuchung.

### Nachtrag 2026-11-27 — zwei weitere Fälligkeiten, beide belegt

**Der Befund ist nicht mehr einmalig.** Am 2026-11-27 ist derselbe Check **zweimal innerhalb einer
Stunde** gefallen, mit wörtlich derselben Meldung:

| Lauf | Auslöser | Zeit (UTC) | Lage |
|---|---|---|---|
| `46003145481` | Push auf `master` (Merge von PR #125) | 12:58 | 7 von 8 Jobs grün, nur dieser rot |
| `46010614042` | Pull Request #130 | 13:51 | 7 von 8 Jobs grün, alle drei Testläufe eingeschlossen |

Beide Male:

```
Failed to execute git clone --mirror -- git@github.com:area-net-gmbh/contentfly-framework-dist.git
Host key verification failed.
fatal: Could not read from remote repository.
```

**Drei Dinge, die diese zwei Läufe gegenüber dem 2026-09-29 hinzufügen:**

1. **Es trifft nicht nur Pull Requests.** Der erste Fall ist ein Push auf `master`. Der Befund vom
   2026-09-29 stammte aus PR #101, was die Vermutung zuliess, es hänge an der PR-Umgebung. Tut es
   nicht.
2. **Der Inhalt ist als Ursache ausgeschlossen, nicht nur unwahrscheinlich.** PR #130 ändert drei
   Zeilen Markdown unter `an_project/` — kein Code, kein Lock, kein Manifest. Und ein
   `gh run rerun --failed` **auf demselben Commit, ohne jede Änderung**, lief grün durch. Das ist
   die zweite Wiederholung dieses Musters nach dem 2026-09-29.
3. **Die Fehlstelle ist genauer eingegrenzt — das verengt die offene Frage unten.** Das Log nennt
   das Ziel des Klons: `/github/home/.composer/cache/vcs/git-github.com-area-net-gmbh-contentfly-framework-dist.git`.
   Der Fehlschlag passiert also, **während Composer seinen VCS-Spiegel anlegt**, nicht beim
   Beziehen eines `dist`-Pakets. Die Frage „kommt Composer beim ersten Mal über eine andere Ebene
   und fällt nur im Grenzfall auf SSH" ist damit nicht beantwortet, aber auf eine Stelle
   zusammengezogen: den VCS-Treiber, der für ein GitHub-Repository die SSH-Form wählt, obwohl
   `PAKET_REPO_URL` HTTPS ist.

**Was die Häufigkeit für die Dringlichkeit bedeutet.** Die Begründung oben — ein erforderlicher
Check, der grundlos rot wird, erzieht zum Re-Run — war am 2026-09-29 ein Argument aus einem
einzelnen Fall. Mit zwei Fälligkeiten an einem Tag ist sie eine Beobachtung: Beide Male **war** der
Re-Run die Reaktion, und beide Male war er richtig. Genau diese Trefferquote ist das Problem, nicht
die verlorene Zeit.

**Offen und Teil des Tasks:** Warum überhaupt SSH? `000-000-0087` hat festgehalten, dass das
Paket-Repository öffentlich ist und der Lauf **ohne Zugang** funktioniert — die URL im Skript ist
HTTPS. Wenn im Job trotzdem SSH benutzt wird, kommt das von woanders (Composer-Konfiguration,
`insteadOf`, der Lock). Das zu klären ist wertvoller als einen Host-Key nachzulegen.

## Acceptance criteria
- [ ] Die Ursache ist benannt: **warum** der Klon über SSH lief, obwohl `PAKET_REPO_URL` HTTPS ist. Die Antwort steht im Skript oder im Job, wo sie beim nächsten Mal gefunden wird.
- [ ] Der Fehlschlag kann nicht mehr aus diesem Grund auftreten — entweder weil der Weg nachweislich HTTPS ist, oder weil der Host-Key hinterlegt wird (`ssh-keyscan github.com >> ~/.ssh/known_hosts`), oder beides.
- [ ] Wird ein Host-Key hinterlegt, ist er **verifiziert** und nicht blind übernommen: gegen die von GitHub veröffentlichten Fingerprints geprüft, mit Fundstelle. `StrictHostKeyChecking=no` ist keine Lösung — das schaltet die Prüfung ab, statt sie zu bestehen.
- [ ] Die Änderung ist im Skript begründet, nicht nur im Job: `tools/ci/bezugsweg-pruefen.sh` erklärt seine Laufzeit selbst, so wie die übrigen Skripte unter `tools/ci/`.

## Verification
Der Job läuft in einem Pull Request grün — das allein beweist nichts, er lief vorher auch meistens
grün. **Aussagekräftig ist die Gegenprobe:** den Klon im Container einmal erzwungen über SSH
fahren, mit leerem `known_hosts`, und zeigen, dass er **vor** der Änderung scheitert und danach
nicht. Ohne diese Messung ist der Task eine Vermutung mit einem Commit daran.

## Abgrenzung
**Die fehlende Prüfsumme beim Composer-Installer** (`tools/ci/install-composer.sh` führt sie
ausdrücklich als ungelöst) gehört nicht hierher. Sie ist verwandt — beide betreffen Vertrauen in
etwas aus dem Netz —, aber sie ist eine eigene Entscheidung über eine Bezugsquelle.
