---
id: 000-000-0020
title: POST /api/schema ist mit Symfony 4 nicht mehr erreichbar
status: todo
depends_on: [006-002-0003]
---

# POST /api/schema ist mit Symfony 4 nicht mehr erreichbar

## Context
Gefunden mit `006-002-0003` beim Stack-Wechsel:

```
No route found for "POST /api/schema": Method Not Allowed (Allow: OPTIONS, GET)
```

Unter Symfony 3.4 nahm die Route auch POST an, unter 4.4 nicht mehr. `RouteSecurityApiTest`
scheitert daran; weitere Aufrufer sind wahrscheinlich.

## Umfang
Zu klären ist zuerst, **welches Verhalten das gemeinte ist**:

- War POST je beabsichtigt? `/api/schema` liest nur — GET ist die passende Methode, und die
  Suite ruft es überwiegend per GET auf.
- Oder verlassen sich Clients darauf? Das Schema ist die Datei, an der jeder Client hängt;
  eine Methodenänderung ist für sie ein Bruch.

Erst danach die Entscheidung: Route um POST erweitern, oder POST als nie unterstützt
dokumentieren und die Aufrufer nachziehen.

**Ein Verdacht, der zu prüfen ist:** Unter Symfony 3.4 war das Routing toleranter. Wenn POST
dort nie definiert war und nur durchrutschte, ist 4.4 im Recht — dann ist das keine
Regression, sondern eine stillschweigende Zusicherung, die jetzt auffliegt.

## Abgrenzung
Keine Änderung am Inhalt des Schemas. Nur die Frage, über welche Methode es erreichbar ist.

## Acceptance criteria
- [ ] Geklärt und begründet, ob POST unterstützt sein soll.
- [ ] Alle Aufrufer im Repo (Tests eingeschlossen) benutzen die entschiedene Methode.
- [ ] Ist POST nicht mehr unterstützt, steht es in `an_project/docs/breaking-changes.md`.
- [ ] `RouteSecurityApiTest::testEineGesicherteRouteAntwortetMitToken` ist grün.

## Verification
Die Suite gegen den neuen Baum. Zusätzlich `grep` über `api/schema` im ganzen Repo — jede
Fundstelle benutzt die entschiedene Methode.
