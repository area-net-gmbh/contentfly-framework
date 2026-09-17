---
id: 011-004-0002
title: Das Release beschreiben: Bruchstellen, Erfolgskriterien, Upgrade-Pfad
status: todo
depends_on: [011-004-0001]
---

# Das Release beschreiben: Bruchstellen, Erfolgskriterien, Upgrade-Pfad

## Context
**Das Epic sagt, woran es sich messen lassen will — bisher steht nirgends, ob es das eingelöst
hat.** Dieser Task schreibt die Bilanz, und zwar mit Belegen statt Behauptungen.

Drei Teile:

- **Die Bruchstellen vollständig.** `breaking-changes.md` ist die Liste, an der ein Projekt
  arbeitet. Sie trägt heute 119 Einträge; `migration.md` nennt die Zahl im Kopf, und
  `MigrationGuideTest` hält beide zusammen. Zu prüfen ist, ob der Envelope-Bruch aus `011-001`
  **und** alles aus den Epics `009` bis `014` darin steht — nicht, ob die Zahl stimmt.
- **Die Erfolgskriterien des Epics**, jedes mit dem Beleg, der es trägt: Testlauf, Messung oder
  Dokument.
- **Der Upgrade-Pfad.** Symfony 8.4 LTS wird für Nov 2027 erwartet. Was den Sprung zu einem
  reinen Constraint-Bump macht, ist das bestehende Gate „0 Deprecations" — das gehört
  aufgeschrieben, solange der Grund noch frisch ist.

## Acceptance criteria
- [ ] Jedes Erfolgskriterium des Epics `011` hat einen benannten Beleg — oder ist ausdrücklich als offen gekennzeichnet.
- [ ] Geprüft und festgehalten, dass das Register den Envelope-Bruch und die Epics `009`–`014` trägt; Lücken sind benannt statt stillschweigend gelassen.
- [ ] Der Upgrade-Pfad auf Symfony 8.4 LTS steht in `architecture.md` unter *Key decisions*, mit dem, was ihn absichert und was ihn gefährden würde.
- [ ] Der Leitfaden nennt seine Grösse richtig — **geprüft**, nicht geschätzt.

## Verification
`MigrationGuideTest` grün. Die Bilanz ist gegen das Epic gelesen: Zu jedem Kriterium existiert der
genannte Beleg wirklich.
