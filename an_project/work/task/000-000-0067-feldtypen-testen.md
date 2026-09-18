---
id: 000-000-0067
title: Feldtypen testen, die Framework und Vorlage selbst nicht benutzen
status: todo
depends_on: []
---

# Feldtypen testen, die Framework und Vorlage selbst nicht benutzen

## Context
**Gefunden bei der ersten Coverage-Messung (`000-000-0056`, 2026-09-18).** `Classes/Types` ist der
am schwächsten abgedeckte Bereich: **34 %, 541 Zeilen offen.** `MultifileType` 6 %, `CheckboxType`
4 %, `RadioType` 6 %, `OnejoinType` 7 %, `FileType` 11 %, `PermissionsType` 14 %.

**Die Ursache ist strukturell:** Keine Entity des Frameworks oder der Vorlage hat ein Feld dieser
Typen — also ruft kein Test ihr `toDatabase()` oder `fromDatabase()` auf. Projekte benutzen sie aber;
jeder Wert eines solchen Feldes läuft durch diesen Code, beim Schreiben wie beim Lesen, und darin
stecken auch Rechteprüfungen (`pim_blocked` für nicht lesbare Verknüpfungen).

## Acceptance criteria
- [ ] Die Vorlage zeigt je Typ ein Beispielfeld — dieselbe Begründung wie bei `Core\ExampleI18n` (`000-000-0059`): Was die Vorlage nicht zeigt, testet niemand.
- [ ] Je Typ ein Integrationstest: schreiben, lesen, und bei Verknüpfungen das Verhalten ohne Leserecht auf das Ziel.
- [ ] Die Coverage von `Classes/Types` ist danach neu gemessen und im Task festgehalten — als Zahl, nicht als Ziel.

## Verification
Die neuen Tests über HTTP, und der Coverage-Lauf aus `0056`.
