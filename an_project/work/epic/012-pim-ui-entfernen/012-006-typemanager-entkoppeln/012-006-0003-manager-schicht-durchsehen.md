---
id: 012-006-0003
title: Verbleibende Manager auf UI-Reste durchsehen
status: todo
depends_on: [012-006-0002]
---

# Verbleibende Manager auf UI-Reste durchsehen

## Context
Letzter Durchgang durch `Classes/Manager/`, nachdem 012-001 bis 012-005 durch sind — der Ort, an dem UI-Wissen am ehesten unbemerkt liegen bleibt.

## Acceptance criteria
- [ ] `RouteManager`, `ConsoleManager` und `LoginManager` sind auf UI-Reste geprüft und bereinigt.
- [ ] Kein Manager referenziert mehr Templates, Assets oder Widgets.
- [ ] Die `_secured`-Semantik des `RouteManager` ist unangetastet — sie wird in Epic 009 gebraucht.
- [ ] Was gelöschte Twig-Templates erwartet hat, ist verschwunden.

## Verification
`grep -rn "twig\|assets\|widget" lib/contentfly/Classes/Manager` liefert keine Treffer. Anwendung bootet, Testnetz-Vorstufe grün.
