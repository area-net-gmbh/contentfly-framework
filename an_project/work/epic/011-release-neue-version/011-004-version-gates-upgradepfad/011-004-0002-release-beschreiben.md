---
id: 011-004-0002
title: Das Release beschreiben: Bruchstellen, Erfolgskriterien, Upgrade-Pfad
status: review
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
- [x] Jedes Erfolgskriterium des Epics `011` hat einen benannten Beleg — oder ist ausdrücklich als offen gekennzeichnet.
- [x] Geprüft und festgehalten, dass das Register den Envelope-Bruch und die Epics `009`–`014` trägt; Lücken sind benannt statt stillschweigend gelassen.
- [x] Der Upgrade-Pfad auf Symfony 8.4 LTS steht in `architecture.md` unter *Key decisions*, mit dem, was ihn absichert und was ihn gefährden würde.
- [x] Der Leitfaden nennt seine Grösse richtig — **geprüft**, nicht geschätzt.

## Verification
`MigrationGuideTest` grün. Die Bilanz ist gegen das Epic gelesen: Zu jedem Kriterium existiert der
genannte Beleg wirklich.

## Ergebnis
Die Bilanz steht im Epic `011` als Abschnitt *Bilanz — jedes Kriterium mit seinem Beleg*, der
Upgrade-Pfad in `architecture.md` unter *Key decisions* (2026-09-17).

**Drei Dinge, die die Prüfung zutage gefördert hat und die eine Abhak-Liste verfehlt hätte:**

1. **Fünf Pakete stehen auf `v3.7.x` und sind trotzdem keine Symfony-3-Komponenten.** Es sind die
   `*-contracts`, die eigenständig versionieren; `service-contracts` 3.x ist die Linie von
   Symfony 7.4. Wer das Kriterium „keine Symfony-2/3-Komponente" mit einer Versionsprüfung
   nachrechnet, kommt zum gegenteiligen Schluss — deshalb steht die Erklärung in der Bilanz.
2. **Epic `014` fehlt im Register, und das ist richtig.** Die Umbenennungen betrafen nur Namen,
   die nie in einem Release waren. Ein Bestandsprojekt hat sie nie gesehen.
3. **Die Zielplattform ist halb belegt.** Das Gate aus `0001` prüft die Constraint-Seite, nicht
   einen Lauf auf 8.5. In der Bilanz steht deshalb ⚠️ und nicht ✅.

Die Grössenangabe des Leitfadens ist nachgezählt: 119 Einträge, 14 Abschnitte — die Zahlen
stimmten, das Stand-Datum war einen Tag alt und ist korrigiert.
