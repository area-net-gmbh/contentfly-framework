---
id: 000-000-0052
title: actions/checkout läuft auf einem abgekündigten Node
status: todo
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
- [ ] Alle Vorkommen von `actions/checkout` stehen auf einer Fassung, die Node 24 selbst erklärt.
- [ ] Ein Lauf ist ohne diese Warnung durchgegangen — belegt, nicht behauptet.
- [ ] Geprüft, ob weitere Actions im Baum dieselbe Meldung erzeugen; heute ist `checkout` die einzige, aber das gilt nur, solange niemand eine zweite hinzufügt.

## Verification
Ein Lauf der Pipeline und ein Trockenlauf des Veröffentlichungs-Workflows, beide ohne die
Annotation „Node.js 20 is deprecated".

## Hinweis
Der Sprung ist vermutlich ein Einzeiler je Vorkommen. Das ist kein Grund, ihn ohne Ticket zu
machen — die Regel dieses Projekts kennt keine Ausnahme für kleine Änderungen, und gerade eine
Versionsänderung an der CI-Infrastruktur will beim Suchen eines späteren Fehlers auffindbar sein.
