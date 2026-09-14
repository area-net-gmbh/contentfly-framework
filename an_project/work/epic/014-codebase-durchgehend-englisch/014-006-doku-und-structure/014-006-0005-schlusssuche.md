---
id: 014-006-0005
title: Schlusssuche nach alten Namen über alle Docs
status: todo
depends_on: [014-006-0004]
---

# Schlusssuche nach alten Namen über alle Docs

## Context
Das Ziel der Story ist messbar: Eine Suche nach jedem alten Namen findet in keinem Dokument mehr
einen Treffer, ausgenommen `CHANGELOG.md` und abgeschlossene Work-Items. Die Tasks `0001` bis
`0004` räumen Datei für Datei auf. Dieser Task prüft das Ganze, auch dort, wo kein Task hinsah:
`README.md`, `tests/README.md`, `CLAUDE.md`, `tools/` und `.gitlab-ci.yml`.

## Acceptance criteria
- [ ] Die Suche nach der vollständigen Liste alter Namen findet ausserhalb von `CHANGELOG.md` und
  `an_project/work/` nichts. Dazu gehören die Tabelle des Epics, die umbenannten Klassen, Methoden,
  Testklassen und Helfer aus `014-001` bis `014-005` und die deutschen Begriffe, die wie alte
  Klassennamen aussehen.
- [ ] Jeder Code-Verweis in `STRUCTURE.md`, `README.md`, `tests/README.md` und `an_project/docs/`
  löst sich auf.
- [ ] In `tools/` und `.gitlab-ci.yml` stimmen die Verweise auf umbenannte Namen. Skriptnamen und
  Kommentare dort bleiben (Epic, *Nicht Teil des Epics*).
- [ ] Die Liste der alten Namen und das Prüfskript liegen der Ergebnis-Notiz bei, damit die Suche
  wiederholbar ist.
- [ ] Volle Suite, PHPStan und Deprecation-Gate grün.

## Verification
Suche mit der vollständigen Liste über den ganzen Baum ohne `vendor/`, `.an_framework/`,
`CHANGELOG.md` und `an_project/work/`. Die Suche wird gegen einen absichtlich eingesetzten alten
Namen gegengeprüft. Auflösungsskript über alle Docs. Volle Suite.
