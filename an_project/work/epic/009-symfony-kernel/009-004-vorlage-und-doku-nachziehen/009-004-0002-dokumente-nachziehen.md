---
id: 009-004-0002
title: Die Dokumente auf den neuen Kernel nachziehen
status: todo
depends_on: []
---

# Die Dokumente auf den neuen Kernel nachziehen

## Context
Sieben Dokumente nennen Silex noch als laufenden Stand. Gemessen:

| Datei | Fundstellen |
|---|---|
| `an_project/docs/abhaengigkeiten-inventar.md` | 11 |
| `an_project/docs/deployment.md` | 4 |
| `an_project/docs/tech-stack.md` | 2 |
| `an_project/docs/technical.md` | 2 |
| `tests/README.md` | 2 |
| `an_project/docs/architecture.md` | 1 |
| `README.md` | 1 |

**Nicht jede Fundstelle ist falsch.** `abhaengigkeiten-inventar.md` ist ein datierter,
generierter Schnappschuss — er behauptet nichts über den Jetzt-Zustand, und ihn umzuschreiben
hiesse, eine Messung zu fälschen. Dasselbe gilt für jede Stelle, die Silex in der
Vergangenheitsform als Begründung nennt: Sie erklärt, warum etwas so ist, und bleibt richtig.

Zu ändern ist, was Silex als **laufenden** Stand beschreibt. `technical.md` hat dazu einen
ganzen Abschnitt über Prioritäten bei `before()`/`after()`, der mit „Silex nimmt …" beginnt.

## Acceptance criteria
- [ ] Jede der Fundstellen ist angesehen und entweder nachgezogen oder als richtig
      stehengelassen — mit Begründung im Ergebnis, nicht stillschweigend.
- [ ] `abhaengigkeiten-inventar.md` bleibt unangetastet; die Begründung steht im Ergebnis.
- [ ] `technical.md` beschreibt die Middleware-Reihenfolge auf dem neuen Kernel und verweist
      auf den Test, der sie nachweist (`HookReihenfolgeTest`).
- [ ] `tech-stack.md` beschreibt Symfony 7.4 als **erreichten**, nicht als angestrebten Stand;
      der Satz über „aktuell noch Silex 2" ist aufgelöst.
- [ ] Die Zusicherung „deprecation-frei bauen" aus `tech-stack.md` ist mit dem Stand belegt, den
      `009-003` hergestellt hat.

## Verification
`grep -ri silex` über die Dokumente: Was übrig bleibt, steht in der Vergangenheitsform oder in
einem datierten Schnappschuss. Jede verbliebene Stelle ist im Ergebnis genannt.
