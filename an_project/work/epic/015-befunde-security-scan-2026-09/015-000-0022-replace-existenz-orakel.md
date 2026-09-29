---
id: 015-000-0022
title: /api/replace verrät die Existenz von Datensätzen ohne Leserecht
status: done
depends_on: []
---

# /api/replace verrät die Existenz von Datensätzen ohne Leserecht

## Context
**Security-Scan 2026-09, LOW. Finding F37.**

`ApiController::replaceAction` (`Controller/ApiController.php:810`) übergibt `entity` und `id` aus
dem Body ohne Leserecht-Prüfung an `getRepository()->find()` und verzweigt dann: Update, wenn der
Datensatz existiert, sonst Insert. Die beiden Zweige scheitern für einen Aufrufer ohne Rechte mit
verschiedenen Codes: `doUpdate` → `getSingle` mit `contentfly_general_access_denied`, `doInsert`
mit `contentfly_general_permission_denied`.

Folge: Jeder angemeldete Benutzer kann für eine Entity, die er nicht lesen darf, prüfen, welche IDs
existieren. Mit der Standard-ID-Strategie `auto` (Ganzzahlen) erfährt er so auch, wie viele
Datensätze es gibt. Inhalte gibt die Antwort nicht preis.

## Acceptance criteria
- [x] `replaceAction` prüft die Entity gegen das Schema und `Permission::isReadable`/`isWritable` **vor** der Suche im Repository.
- [x] Ein Aufrufer ohne Rechte bekommt in beiden Fällen (ID existiert oder nicht) dieselbe Antwort.

## Verification
Integrationstest: Benutzer ohne Rechte auf eine Entity sendet `/api/replace` mit einer
existierenden und einer nicht existierenden ID. Vor dem Fix unterscheiden sich die Fehlercodes,
nach dem Fix sind Status und Körper gleich.
