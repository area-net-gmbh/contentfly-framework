---
id: 012-002-0001
title: Installationsschritte des InstallControllers erfassen
status: todo
depends_on: []
---

# Installationsschritte des InstallControllers erfassen

## Context
Bevor der `InstallController` (203 Zeilen) verschwindet, muss belegt sein, was er tut — sonst fehlt dem Console-Command hinterher ein Schritt, den niemand vermisst, bis eine Neuinstallation scheitert.

## Acceptance criteria
- [ ] Jeder Schritt ist benannt: Schema/Tabellen, erster Benutzer, Konfigurationsdateien, Verzeichnisse und Rechte, Plugin-Registrierung.
- [ ] Für jeden Schritt ist festgehalten, welche Eingaben er braucht und was er voraussetzt.
- [ ] Die Aufstellung liegt als Grundlage im Task-/Story-Kontext vor, nicht nur im Kopf.

## Verification
Die Aufstellung wird gegen den Code gegengelesen: Jede Methode des `InstallController` ist einem Schritt zugeordnet oder als überflüssig markiert.
