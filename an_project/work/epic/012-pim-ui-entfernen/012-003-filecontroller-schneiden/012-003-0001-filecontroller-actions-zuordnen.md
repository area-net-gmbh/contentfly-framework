---
id: 012-003-0001
title: Actions des FileControllers zuordnen: API oder UI
status: todo
depends_on: []
---

# Actions des FileControllers zuordnen: API oder UI

## Context
Der `FileController` (482 Zeilen) trägt API-Dateifunktionen und Admin-Dateiverwaltung in einer Datei. Ohne saubere Zuordnung wird beim Löschen Sync-Funktion mit entfernt.

## Acceptance criteria
- [ ] Jede Action ist als API-Funktion oder UI-Funktion eingestuft, mit Begründung.
- [ ] Für die API-Funktionen ist belegt, wer sie aufruft (Sync, Apps, `custom/`-Vorlage).
- [ ] `lib/contentfly/Classes/File/` ist in dieselbe Zuordnung einbezogen.
- [ ] Die Liste ist festgehalten und für den Migrationsleitfaden (Epic 007) verwendbar.

## Verification
Die Zuordnung wird gegen die Routen des `FileControllerProvider` gegengelesen — keine Route bleibt unklassifiziert.
