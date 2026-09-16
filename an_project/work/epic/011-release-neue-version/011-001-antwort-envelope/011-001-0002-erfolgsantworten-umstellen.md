---
id: 011-001-0002
title: Erfolgsantworten auf data/errors/meta umstellen
status: todo
depends_on: [011-001-0001]
---

# Erfolgsantworten auf data/errors/meta umstellen

## Context
Der Trichter ist `ApiController::renderResponse()`: Er hängt heute `version` und `hash` an ein
Array, das jeder Aufrufer selbst zusammenbaut — mal mit `ts`, mal mit `lastModified`, mal mit
`id` neben `data`. Genau daraus entstehen die sieben Formen.

Die Umstellung gehört in den Trichter, nicht in 18 Aufrufer: `data` immer vorhanden (auch `null`),
`errors` immer vorhanden (bei Erfolg `null`), und alles, was nicht Nutzlast ist, unter `meta` —
`ts`, `version`, `hash`, `totalItems`, `itemsPerPage`, `lastModified`.

**Die Charakterisierungstests aus Epic `008` werden dabei rot.** Das ist der Zweck der Übung, aber
`technical.md` verlangt: „Eine Testanpassung ist ein Verhaltenswechsel und braucht eine
Begründung." Jede angepasste Erwartung nennt die Zeile der Zieltabelle, aus der sie folgt.

## Acceptance criteria
- [ ] Jeder Erfolgsfall der in `0001` festgelegten Endpunkte antwortet in der Zielform; `data` und `errors` sind immer vorhanden.
- [ ] Kein Endpunkt trägt mehr Nutzlast und Metadaten auf derselben Ebene.
- [ ] Die Statuscodes sind unverändert — diese Story ändert die Form, nicht die Semantik.
- [ ] Jede angepasste Testerwartung ist im Test begründet, mit Verweis auf die Zieltabelle.
- [ ] `breaking-changes.md` trägt den Bruch für die Erfolgsantworten, `migration.md` den Schritt für Clients; `MigrationGuideTest` grün.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Die Aufzeichnung eines Endpunkts vorher/nachher im Test festhalten. Gegenprobe: Ein Testclient
liest `data` und `meta.version` bei jedem umgestellten Endpunkt mit demselben Code.
