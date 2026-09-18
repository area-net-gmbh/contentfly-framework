---
id: 000-000-0061
title: /api/tree, /api/tree2 und /api/deleted prüfen kein Leserecht
status: review
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
- [x] `/api/tree` und `/api/tree2` lehnen ohne Leserecht mit 403 ab, wie `/api/list`.
- [x] Beide verengen bei `OWN`/`GROUP` wie `getList()`. Bei `tree2` klärt der Task, wie mit Knoten umgegangen wird, deren Eltern nicht sichtbar sind.
- [x] `/api/deleted` liefert nur Einträge zu Entities, die der Benutzer lesen darf.
- [x] Die Rechtematrix in `PermissionMatrixApiTest.php` deckt die drei Routen ab.

## Verification
Die Gegenprobe oben als Integrationstest: vor der Änderung rot, danach grün. Dazu je ein Fall
`OWN` und `GROUP`.

## Ergebnis
**Alle drei Routen prüfen jetzt das Leserecht.** `/api/tree` und `/api/tree2` antworten ohne
Leserecht mit 403, wie `/api/list`, und verengen bei `OWN`/`GROUP` mit denselben Bedingungen wie
`getList()`. `/api/deleted` überspringt Entities ohne Leserecht.

**Entschieden: Ein Knoten unter einem unsichtbaren Elternknoten ist ebenfalls unsichtbar** — bei
beiden Routen. `getTree()` erreicht Kinder ohnehin nur über ihren Elternknoten; `getTree2()` baut
den Baum in `treeSort()` von der Wurzel her, ein Knoten ohne sichtbaren Elternknoten hat dort
keinen Platz. Ihn stattdessen an die oberste Ebene zu hängen, hätte eine Struktur gezeigt, die es
nicht gibt. Festgehalten in `testATreeNodeBelowAHiddenParentIsHiddenToo()`.

**`/api/deleted` verengt nur auf Entity-Ebene**, nicht auf Eigentümerschaft: Der Datensatz ist
gelöscht, die Log-Zeile ist alles, was von ihm übrig ist.

**Tests in `PermissionMatrixApiTest.php`**: beide Tree-Routen × vier Stufen (8 Fälle), der
verborgene Elternknoten, das Löschprotokoll. **Vor dem Fix acht rot**, danach grün; die ganze
Suite 669 Tests grün, PHPStan ohne Fehler. `Tree2LangBindingTest` (aus `0062`) läuft jetzt mit
einem Admin, weil `getTree2()` einen Benutzer braucht.
