---
id: 011-001-0004
title: Restliche Endpunkte und Abnahme des Envelopes
status: todo
depends_on: [011-001-0003]
---

# Restliche Endpunkte und Abnahme des Envelopes

## Context
Was `0001` als Geltungsbereich entschieden hat und in `0002`/`0003` noch nicht umgestellt ist,
kommt hier — je nach Entscheidung `/auth`, `/file`, `/system`, oder nichts davon.

Dann die Abnahme, und die ist der eigentliche Zweck des Epics: **Ein Client muss jeden Endpunkt
mit demselben Code auswerten können**, Erfolg wie Fehler. Solange das nicht an einem Stück
gemessen ist, bleibt die Vereinheitlichung eine Behauptung.

## Acceptance criteria
- [ ] Die restlichen Endpunkte aus dem Geltungsbereich antworten in der Zielform — oder es ist festgehalten, dass sie bewusst aussen bleiben, mit Begründung im Register.
- [ ] Ein Test wertet **jeden** Endpunkt des Geltungsbereichs mit einer einzigen Auswertung aus: `data`, `errors`, `meta` — je einmal im Erfolgs- und im Fehlerfall.
- [ ] `api-envelope.md` beschreibt den erreichten Zustand, nicht mehr den geplanten.
- [ ] `breaking-changes.md` und `migration.md` tragen den Bruch vollständig; die Zahl im Kopf des Leitfadens stimmt.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Der Abnahmetest über alle Endpunkte. Dazu ein Blick aus Clientsicht: die Tabelle *vorher → nachher*
im Leitfaden gegen die tatsächlichen Antworten.
