---
id: 014-006-0000
title: Doku und STRUCTURE.md nachziehen
status: todo
depends_on: [014-005-0000]
---

# Doku und STRUCTURE.md nachziehen

## Goal
Jeder Name in `STRUCTURE.md`, `README.md` und `an_project/docs/` stimmt mit dem Code überein.
Das Glossar der deutschen Bezeichner in `STRUCTURE.md` entfällt. `breaking-changes.md` und
`migration.md` nennen die englischen Namen. Weil keiner der deutschen Namen je in einem
Release war, braucht es dort keinen Umbenennungs-Abschnitt.

`uebergabe-security.md` verweist auf den neuen Stand. Die Übergabe erfolgt mit einem neuen Tag
nach diesem Epic.

Die Prosa in `an_project/` bleibt deutsch (Sprachregel des Frameworks). Am Ende findet eine
Suche nach jedem alten Namen aus der Tabelle im Epic in keinem Dokument mehr einen Treffer,
ausgenommen `CHANGELOG.md` und abgeschlossene Work-Items, die Geschichte sind.

## Tasks
- [ ] 014-006-0001 — STRUCTURE.md auf die englischen Namen
- [ ] 014-006-0002 — Entwickler-Docs auf die englischen Namen
- [ ] 014-006-0003 — Migrations-Docs auf die englischen Namen
- [ ] 014-006-0004 — Übergabenotiz für die Sicherheitsprüfung auf den neuen Stand
- [ ] 014-006-0005 — Schlusssuche nach alten Namen über alle Docs
