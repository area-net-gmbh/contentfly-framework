---
id: 000-000-0052
title: actions/checkout läuft auf einem abgekündigten Node
status: done
depends_on: []
---

# actions/checkout läuft auf einem abgekündigten Node

## Context
**Gefunden bei `011-002-0003`** am 2026-09-17, im ersten Lauf des Veröffentlichungs-Workflows.
Der Lauf war grün und meldete:

```
Node.js 20 is deprecated. The following actions target Node.js 20 but are being
forced to run on Node.js 24: actions/checkout@v4
```

`actions/checkout@v4` erklärt in seinem Manifest Node 20 als Laufzeit. Die Runner führen es
inzwischen **zwangsweise auf Node 24** aus — es läuft also, aber nicht mehr so, wie die Action es
von sich behauptet.

**Warum das jetzt ein Ticket ist und keine Notiz:** Eine Warnung, die in jedem Lauf steht, wird
nach dem dritten Mal nicht mehr gelesen — und dann fällt auch die nächste nicht auf, die etwas
anderes sagt. Dasselbe Argument, aus dem dieses Projekt seine Gates blockierend stellt.

Betroffen sind beide Workflows: `.github/workflows/pipeline.yml` (vier Vorkommen) und
`.github/workflows/paket.yml` (eines).

## Acceptance criteria
- [x] Alle Vorkommen von `actions/checkout` stehen auf einer Fassung, die Node 24 selbst erklärt.
- [x] Ein Lauf ist ohne diese Warnung durchgegangen — belegt, nicht behauptet.
- [x] Geprüft, ob weitere Actions im Baum dieselbe Meldung erzeugen; heute ist `checkout` die einzige, aber das gilt nur, solange niemand eine zweite hinzufügt.

## Verification
Ein Lauf der Pipeline und ein Trockenlauf des Veröffentlichungs-Workflows, beide ohne die
Annotation „Node.js 20 is deprecated".

## Hinweis
Der Sprung ist vermutlich ein Einzeiler je Vorkommen. Das ist kein Grund, ihn ohne Ticket zu
machen — die Regel dieses Projekts kennt keine Ausnahme für kleine Änderungen, und gerade eine
Versionsänderung an der CI-Infrastruktur will beim Suchen eines späteren Fehlers auffindbar sein.

## Ergebnis
**Alle sechs Vorkommen stehen auf `actions/checkout@v6`.**

**Sechs, nicht fünf** — der Context oben nennt vier in `pipeline.yml`, es sind fünf. Der
`bezugsweg`-Job ist nach dem Anlegen dieses Tickets dazugekommen (`011-002-0004`). Genau dafür
zählt man beim Umsetzen nach, statt die Zahl aus dem Ticket zu übernehmen.

**Warum `@v6` und nicht `@v5`:** Beide erklären `using: node24`. Ihre `action.yml` sind
**byte-identisch** — gemessen, nicht vermutet:

```
diff <(curl -sS .../checkout/v5/action.yml) <(curl -sS .../checkout/v6/action.yml)  →  exit 0
```

Gleiche Eingaben, gleiche Vorgaben; der Sprung ist ein reiner Laufzeitwechsel, und `@v6` spart
den nächsten. Eigens geprüft, weil `paket.yml` `fetch-depth: 0` setzt und der Subtree-Split die
volle Historie braucht: Die Option ist unverändert und wird weiterhin gelesen.

`checkout` ist die **einzige** fremde Action in beiden Dateien. Die Begründung steht jetzt im
Kopf von `pipeline.yml`, mitsamt dem `diff`-Aufruf zum Nachprüfen — damit die nächste Action
nicht wieder eine mitbringt, die ihre Laufzeit nicht selbst erklärt.

**Der Lauf ist belegt.** Das zweite Acceptance-Kriterium verlangte einen Lauf *ohne* die
Annotation; der Pull Request ist auf `master` gemergt und sein Lauf meldet „Node.js 20 is
deprecated" nicht mehr — vom Auftraggeber in GitHub Actions eingesehen und am 2026-09-18
bestätigt. Damit ist die Box zu.
