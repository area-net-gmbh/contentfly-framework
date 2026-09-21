---
id: 000-000-0070
title: Gruppenrechte schreiben, ohne PIM\Tag stillschweigend freizugeben
status: todo
depends_on: []
---

# Gruppenrechte schreiben, ohne PIM\Tag stillschweigend freizugeben

## Context
**Gefunden in `000-000-0067` (2026-09-21).** `PermissionsType::toDatabase()` legt bei **jedem**
Schreiben der Rechte einer Gruppe zusätzlich eine Zeile für `PIM\Tag` an: lesen, schreiben und
löschen auf `ALL` — unabhängig davon, was der Request verlangt. Wer eine Gruppe über
`/api/insert` oder `/api/update` mit `permissions` anlegt, gibt ihr damit ungefragt vollen Zugriff
auf alle Tags.

Vermutlich ein Rest der gestrichenen PIM-Oberfläche (Epic `012`), die Tags an Dateien brauchte.
`FieldTypeApiTest::testWritingGroupPermissionsAlsoGrantsFullAccessToTags` hält das heutige
Verhalten fest, damit die Änderung sichtbar wird.

## Acceptance criteria
- [ ] Entschieden: Die Zeile entfällt, oder sie bleibt als dokumentierte Voreinstellung — mit Begründung.
- [ ] Entfällt sie: Eintrag im Register (`breaking-changes.md`), weil bestehende Gruppen die Rechte verlieren, sobald ihre Rechte neu geschrieben werden.
- [ ] Der Charakterisierungstest ist entsprechend umgedreht oder begründet beibehalten.

## Verification
`FieldTypeApiTest` und die Rechtematrix (`PermissionMatrixApiTest`) grün.
