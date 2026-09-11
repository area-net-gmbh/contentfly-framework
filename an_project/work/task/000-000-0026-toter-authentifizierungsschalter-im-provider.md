---
id: 000-000-0026
title: Der tote Authentifizierungsschalter in BaseControllerProvider
status: todo
depends_on: []
---

# Der tote Authentifizierungsschalter in BaseControllerProvider

## Context
`BaseControllerProvider::isAuthRequiredForPath()` und die Konstante `LOGIN_PATH` ruft **niemand**
— weder im Framework noch in `custom/` noch in `plugins/`.

**Das ist nicht durch Epic `013` tot geworden.** `checkToken()` hat die Methode nie benutzt; sie
stand schon vorher unbenutzt da. Aufgefallen ist sie bei `013-002-0004`, als `checkToken()`
entfiel und die Nachbarschaft durchgesehen wurde — und sie blieb bewusst stehen, weil ein Task
nicht fremdes Totholz mitnimmt, das er nicht selbst erzeugt hat.

Die Methode sieht beim Lesen aus wie eine Stelle, an der man steuert, welche Pfade eine Anmeldung
brauchen. Das tut sie nicht: Diese Entscheidung faellt je Route ueber `isSecure` im
`RouteManager` beziehungsweise ueber den `$checkAuth`-Hook der Provider.

**Zu klaeren, bevor etwas entfernt wird:** Beide sind `protected` bzw. `const` auf einer Klasse,
von der ein Projekt eigene Controller-Provider ableitet. Ein Bestandsprojekt koennte sie
ueberschrieben oder aufgerufen haben — nachsehen laesst sich das hier nicht, also gehoert die
Entfernung in `breaking-changes.md`.

## Acceptance criteria
- [ ] Nachgemessen und festgehalten, dass beide im ganzen Baum keinen Aufrufer haben.
- [ ] Entfernt — oder, falls es einen Grund zum Behalten gibt, ist dieser im Code benannt statt im Kopf.
- [ ] Die Entfernung steht in `an_project/docs/breaking-changes.md`, mit dem Hinweis fuer Projekte, die von `BaseControllerProvider` ableiten.
- [ ] Die volle Suite bleibt gruen.

## Verification
`grep` ueber `lib/`, `custom/`, `plugins/` und `tests/`. Volle Suite, PHPStan.
