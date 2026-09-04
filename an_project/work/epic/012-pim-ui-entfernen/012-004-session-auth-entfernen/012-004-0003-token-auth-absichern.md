---
id: 012-004-0003
title: Token-Authentifizierung mit Tests absichern
status: done
depends_on: [012-004-0002]
---

# Token-Authentifizierung mit Tests absichern

## Context
Nach dem Entfernen der Session ist der Token-Weg der einzige. Ein Fehler darin wäre eine Sicherheitslücke, kein Komfortproblem.

## Acceptance criteria
- [x] Anmeldung, gültiger Token, abgelaufener Token und fehlender Token sind durch Tests abgedeckt.
- [x] Der Zugriff ohne Token wird abgewiesen — nachgewiesen, nicht angenommen.
- [x] Rollen- und Berechtigungsprüfungen verhalten sich wie vor dem Umbau.

## Verification
Testsuite ausführen — grün. Ein Aufruf ohne Token gegen eine geschützte Route liefert den erwarteten Fehlercode.
