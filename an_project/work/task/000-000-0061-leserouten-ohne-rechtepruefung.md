---
id: 000-000-0061
title: /api/tree, /api/tree2 und /api/deleted prüfen kein Leserecht
status: todo
depends_on: []
---

# /api/tree, /api/tree2 und /api/deleted prüfen kein Leserecht

## Context
**Gefunden bei `000-000-0057`, gemessen am 2026-09-18.** Die Routen prüfen nur, **ob** jemand
angemeldet ist, nicht, **was** er lesen darf. `Api::getTree()` und `Api::getTree2()` rufen
`Permission::isReadable()` überhaupt nicht auf.

Gegenprobe am laufenden System, Benutzer **ohne Gruppe** (also ohne jedes Leserecht), Entity
`PIM\Folder`:

| Route | Status | fremder Ordner in der Antwort |
|---|---|---|
| `/api/list` | 403 | nein |
| `/api/tree` | **200** | **ja** |
| `/api/tree2` | **200** | **ja** |

Jeder angemeldete Benutzer liest damit den **vollständigen Inhalt** jeder Tree-Entity.

`Api::getDeleted()` liefert die Löschprotokolle (`model_name`, `model_id`) **aller** Entities,
auch solcher ohne Leserecht — nur Ids, keine Inhalte, aber ohne Prüfung.

## Acceptance criteria
- [ ] `/api/tree` und `/api/tree2` lehnen ohne Leserecht mit 403 ab, wie `/api/list`.
- [ ] Beide verengen bei `OWN`/`GROUP` wie `getList()`. Bei `tree2` klärt der Task, wie mit Knoten umgegangen wird, deren Eltern nicht sichtbar sind.
- [ ] `/api/deleted` liefert nur Einträge zu Entities, die der Benutzer lesen darf.
- [ ] Die Rechtematrix in `PermissionMatrixApiTest.php` deckt die drei Routen ab.

## Verification
Die Gegenprobe oben als Integrationstest: vor der Änderung rot, danach grün. Dazu je ein Fall
`OWN` und `GROUP`.
