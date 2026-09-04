---
id: 012-003-0003
title: API-Dateifunktionen mit Tests absichern
status: todo
depends_on: [012-003-0002]
---

# API-Dateifunktionen mit Tests absichern

## Context
Der Schnitt am `FileController` ist die Stelle mit dem höchsten Risiko, versehentlich Kernfunktion zu entfernen. Tests halten fest, was bleiben muss.

## Acceptance criteria
- [ ] Upload, Download und Auslieferung sind durch Tests abgedeckt.
- [ ] Die Tests laufen gegen den Stand nach dem Schnitt grün.
- [ ] Sie sind so geschrieben, dass sie den Kernel-Wechsel in Epic 009 überleben (keine Silex-Interna).

## Verification
Testsuite ausführen — grün. Ein bewusst eingebauter Fehler in der Upload-Route lässt mindestens einen Test fehlschlagen.
