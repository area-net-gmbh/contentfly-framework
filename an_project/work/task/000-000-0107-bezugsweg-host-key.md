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
