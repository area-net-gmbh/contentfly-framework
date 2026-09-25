---
id: 000-000-0093
title: GROUP in /api/all und /api/count nimmt über users freigegebene Datensätze mit
status: todo
depends_on: []
---

# GROUP in /api/all und /api/count nimmt über users freigegebene Datensätze mit

## Context
**Aus `000-000-0074`.** `reachesRow()` legt fest, was die Stufe `GROUP` erreicht: alles, was `OWN`
erreicht — angelegt **oder** in `users` eingetragen —, und zusätzlich, was für die eigene Gruppe
freigegeben ist. `getList`, `getSingle`, `getTree`, `getTree2`, `getTranslations` und `getQuery`
halten sich daran. **`getAll()` und `getCount()` nicht:** Ihre `GROUP`-Bedingung prüft nur
`userCreated` und `groups`, `users` fehlt.

Gemessen am 2026-09-25 an einem Tag, das einem `GROUP`-Leser über `users` freigegeben ist:

| Endpunkt | Ergebnis |
|---|---|
| `/api/list`, `/api/single` | sichtbar |
| `/api/all` | **fehlt** |
| `/api/count` | **nicht gezählt** |

Ein Sync-Client bekommt solche Datensätze nie, obwohl er sie lesen darf. Kein Leck — der Fehler
verengt zu stark —, aber ein stiller Datenverlust auf dem Client.

## Acceptance criteria
- [ ] `getAll()`: `GROUP` liefert eigene, über `users` freigegebene und für die Gruppe freigegebene Datensätze, keine fremden.
- [ ] `getCount()`: `GROUP` zählt dieselbe Menge.
- [ ] Tests in `PermissionBranchApiTest` für beide Endpunkte, je beide Richtungen; Gegenprobe ohne den Fix rot.
- [ ] Registereintrag unter *API*: Ein `GROUP`-Client bekommt bei `/api/all` und `/api/count` mehr als bisher.

## Verification
Die neuen Tests, dazu die volle Suite. Der Probe-Fall aus `0074` (Tag nur über `users` freigegeben)
erscheint danach in allen vier Endpunkten gleich.
