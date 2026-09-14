---
id: 014-006-0003
title: Migrations-Docs auf die englischen Namen
status: todo
depends_on: [014-006-0002]
---

# Migrations-Docs auf die englischen Namen

## Context
`breaking-changes.md` (33 Treffer), `migration.md` und `pim-annotationen-migration.md` sagen einem
Bestandsprojekt, was es beim Umstieg ändern muss. Stehen dort `CONTENTFLY_PROJEKT`,
`Pfade::projekt()` oder `EntfalleneAttributfelderRector`, stellt ein Projekt auf Namen um, die es
nicht gibt.

Keiner der deutschen Namen war je in einem Release. Die Docs nennen deshalb nur die englischen
Namen; einen Abschnitt „umbenannt von …" gibt es nicht.

## Acceptance criteria
- [ ] Jeder Code-Verweis in den drei Docs stimmt mit dem Code überein, Codebeispiele eingeschlossen.
- [ ] Kein Abschnitt beschreibt eine Umbenennung von deutsch nach englisch.
- [ ] Die Zahl der Register-Einträge und Abschnitte in `breaking-changes.md` ist unverändert.
  `MigrationGuideTest` und `RectorRuleTest`, die diese Docs lesen, sind grün.
- [ ] Die Suche nach den alten Namen findet in den drei Dateien nichts.

## Verification
Suche mit der Liste der alten Namen. Skript, das Code-Verweise auflöst.
`phpunit tests/Unit/Migration` grün, volle Suite grün.
