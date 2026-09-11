---
id: 007-004-0003
title: Die Vollständigkeit prüfbar machen
status: todo
depends_on: [007-004-0002]
---

# Die Vollständigkeit prüfbar machen

## Context
**Die Abnahme der Story verlangt es ausdrücklich:** „Jeder der Brüche ist im Leitfaden
erreichbar — entweder als eigener Schritt oder als Verweis ins Register, und **keiner fällt
heraus**."

**Eine Sorgfaltsfrage bleibt es nur so lange, bis sie einmal schiefgeht.** Das Register wächst
mit jeder Story; in Epic `007` allein sind vier Einträge dazugekommen. Ohne Prüfung fällt ein
neuer Abschnitt genau dann heraus, wenn niemand mehr daran denkt.

## Was geprüft wird

**Abschnittsweise, entschieden am 2026-09-11.** Jeder der Register-Abschnitte muss im Leitfaden
einer Phase zugeordnet sein.

**Und die Gegenrichtung, wie bei den Gates aus `006-005`:** Eine Zuordnung, die auf einen
Abschnitt zeigt, den es nicht mehr gibt, macht den Lauf rot. Sonst bliebe sie stehen, nachdem
der Abschnitt umbenannt oder aufgelöst wurde — und der Leitfaden führte ins Leere.

## Acceptance criteria
- [ ] Ein Test hält fest, dass jeder Abschnitt von `breaking-changes.md` im Leitfaden einer Phase zugeordnet ist.
- [ ] Er hält die Gegenrichtung: Eine Zuordnung ohne Abschnitt macht den Lauf rot.
- [ ] Beide Richtungen sind durch absichtliche Verletzung geprüft.
- [ ] Die Meldung sagt, was zu tun ist — nicht nur, dass etwas fehlt.
- [ ] Die volle Suite bleibt grün.

## Verification
Einen neuen Abschnitt in `breaking-changes.md` anlegen, ohne den Leitfaden zu ergänzen — der Test
muss rot werden. Eine Zuordnung auf einen Abschnitt zeigen lassen, den es nicht gibt — ebenso.
Danach zurücksetzen.
