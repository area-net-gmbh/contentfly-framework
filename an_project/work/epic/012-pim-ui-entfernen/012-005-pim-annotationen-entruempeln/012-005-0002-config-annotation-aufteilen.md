---
id: 012-005-0002
title: Config-Annotation auf datenrelevante Felder reduzieren
status: todo
depends_on: [012-005-0001]
---

# Config-Annotation auf datenrelevante Felder reduzieren

## Context
`Config` mischt Datenmodell und Formularbeschreibung. Von 24 Feldern bleiben die, die API-Verhalten steuern; der Rest beschreibt Masken.

## Acceptance criteria
- [ ] Erhalten bleiben: `excludeFromSync`, `encoded`, `isFilterable`, `unique`, `type`, `i18n_universal`, `sortBy`, `sortOrder`, `sortRestrictTo`.
- [ ] Entfernt sind: `viewMode`, `showInList`, `listShorten`, `hide`, `label`, `labelProperty`, `tab`, `tabs`, `sort`, `isDatalist`, `isSidebar`, `lines`, `accept`.
- [ ] `readonly` und `filter` sind **einzeln geprüft**: Ist es ein UI-Hinweis oder ein Schreibschutz der API? Das Ergebnis ist begründet festgehalten — hier kann versehentlich eine Schutzwirkung verschwinden.
- [ ] Die Entfernung ist hart: kein Feld bleibt wirkungslos stehen (entschieden am 2026-09-04).
- [ ] Die Liste der entfernten Felder liegt für die Rector-Regel in Epic 007 bereit.

## Verification
Anwendung bootet; das von der API ausgelieferte Schema enthält keine UI-Felder mehr.
