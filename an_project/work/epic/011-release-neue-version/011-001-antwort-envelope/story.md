---
id: 011-001-0000
title: Den Antwort-Envelope der API vereinheitlichen
status: in-progress
depends_on: []
---

# Den Antwort-Envelope der API vereinheitlichen

## Goal
**Die API antwortet in einer Form statt in sieben** — `data` / `errors` / `meta`, wie
`an_project/docs/api-envelope.md` es entschieden hat. Heute hat jeder Endpunkt seine eigene
Antwort; nur `version` und `hash` stehen überall gleich. Ein Client kann keine gemeinsame
Auswertung schreiben.

**Diese Story ist der einzige absichtliche Bruch am Draht in diesem Epic.** Deshalb gehört sie
nach vorn: Der Leitfaden, die Vorlage und die Doku (`011-003`) beschreiben danach den Zustand,
der wirklich gilt, und die Version (`011-004`) macht ihn an einer Nummer fest.

**Was dazugehört:**

- Die Tabelle *vorher → nachher* aus `api-envelope.md`, Zeile für Zeile — zehn Endpunkte plus
  den Fehlerfall.
- **Erfolg und Fehler in derselben Form.** `data` ist immer vorhanden, auch als `null`; `errors`
  ist eine Liste; alles, was nicht Nutzlast ist (`ts`, `version`, `hash`, `totalItems`,
  `itemsPerPage`, `lastModified`), steht unter `meta`.
- **Nicht übernommen** werden `success` und `status` (der Statuscode steht im Statuscode) und
  `i18n` (eine Anforderung des Projekts, nicht des Frameworks).
- Die Charakterisierungstests aus Epic `008` werden nachgezogen — **jede Anpassung mit
  Begründung**, weil eine Testanpassung ein Verhaltenswechsel ist (`technical.md`).
- Register (`breaking-changes.md`) und Migrationsleitfaden bekommen den Bruch vollständig, samt
  der Tabelle, an der ein Projekt seine Clients abarbeiten kann.

**Fertig, wenn** kein Endpunkt mehr eine eigene Form hat, die Suite grün ist und ein Client die
Antwort jedes Endpunkts mit demselben Code auswerten kann.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 011-001-0001 — Bestandsaufnahme der Antwortformen und Geltungsbereich des Envelopes
- [ ] 011-001-0002 — Erfolgsantworten auf data/errors/meta umstellen
- [ ] 011-001-0003 — Fehlerantworten in dieselbe Form bringen
- [ ] 011-001-0004 — Restliche Endpunkte und Abnahme des Envelopes
