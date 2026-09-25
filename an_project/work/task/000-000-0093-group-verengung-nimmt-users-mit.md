---
id: 000-000-0093
title: GROUP in /api/all und /api/count nimmt über users freigegebene Datensätze mit
status: review
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
- [x] `getAll()`: `GROUP` liefert eigene, über `users` freigegebene und für die Gruppe freigegebene Datensätze, keine fremden.
- [x] `getCount()`: `GROUP` zählt dieselbe Menge.
- [x] Tests in `PermissionBranchApiTest` für beide Endpunkte, je beide Richtungen; Gegenprobe ohne den Fix rot.
- [x] Registereintrag unter *API*: Ein `GROUP`-Client bekommt bei `/api/all` und `/api/count` mehr als bisher.

## Verification
Die neuen Tests, dazu die volle Suite. Der Probe-Fall aus `0074` (Tag nur über `users` freigegeben)
erscheint danach in allen vier Endpunkten gleich.

## Ergebnis (2026-09-25)
**`GROUP` nimmt in `/api/all` und `/api/count` die über `users` freigegebenen Datensätze mit** — dieselbe
Regel wie `reachesRow()` und die übrigen Lese-Endpunkte.

- `getAll()`: `userCreated = :userCreated OR FIND_IN_SET(:userCreated, users) OR FIND_IN_SET(:userGroup, groups)`.
- `getCount()`: dasselbe als rohes SQL, `groups` weiter in Backticks (`0072`).
- Der Zweig „`GROUP` ohne Gruppe“ bleibt unverändert — er ist unerreichbar (`0074`).

**Tests** in `PermissionBranchApiTest`: Der `getAll`-Test für `GROUP` prüft jetzt auch den über `users`
freigegebenen Tag; neu ist der `getCount`-Test für `GROUP` (3 von 4 Tags). **Gegenprobe:** Gegen den
Code vor dem Fix sind beide rot — der Tag fehlt in `/api/all`, `/api/count` zählt 2 statt 3.

**Registereintrag** unter *API*, mit dem Hinweis für Sync-Clients: Mit `lastModified` kommen die
nachgereichten Datensätze nur, wenn sie seitdem geändert wurden. Leitfaden 141 Einträge, 47 unter *API*.

**Geprüft:** volle Suite auf frischer Installation 860 grün (3 übersprungen wie auf `master`), PHPStan
ohne Fehler, keine Deprecation im Server-Log.
