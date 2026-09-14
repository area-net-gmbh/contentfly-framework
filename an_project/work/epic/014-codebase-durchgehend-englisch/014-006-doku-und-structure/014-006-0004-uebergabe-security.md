---
id: 014-006-0004
title: Übergabenotiz für die Sicherheitsprüfung auf den neuen Stand
status: todo
depends_on: [014-006-0003]
---

# Übergabenotiz für die Sicherheitsprüfung auf den neuen Stand

## Context
`an_project/docs/uebergabe-security.md` (`000-000-0033`) beschreibt den Stand für die
IT-Security und verweist auf den Tag `v2.0.0-pre-security-2026-09-11`. Übergeben wird erst nach
Epic `014`, damit sich Befunde auf die heutigen Namen beziehen. Die Notiz nennt noch alte Namen
(`Tokenhandler::timeoutGilt()`, `ausDatenbank()`, „Anmeldebremse").

## Acceptance criteria
- [ ] Die Notiz nennt nur Namen, die es im Code gibt, und verweist auf `STRUCTURE.md` als
  englischen Einstieg in die Codebase.
- [ ] Die Notiz nennt den neuen Tag nach dem Muster des bestehenden, datiert auf den Tag des
  Setzens. Sie sagt, dass der alte Tag nicht mehr der Prüfstand ist.
- [ ] Die Liste der offenen Punkte ist gegen den heutigen Code gelesen: Was Epic `014` nicht
  geändert hat, bleibt unverändert stehen.
- [ ] Gesetzt wird der Tag **nicht** in diesem Task, sondern nach `/done` des Epics und nur nach
  Rückfrage. Der Task hält den vorgesehenen Namen und den Befehl fest.

## Verification
Suche mit der Liste der alten Namen. Code-Verweise auflösen. Die Notiz einmal von oben nach unten
lesen, ob sie ohne Vorwissen aus dem Epic verständlich ist.
