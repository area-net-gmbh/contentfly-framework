---
id: 007-003-0001
title: Die Entscheidung festschreiben — der $app-Zugriff bleibt
status: todo
depends_on: []
---

# Die Entscheidung festschreiben — der `$app`-Zugriff bleibt

## Context
**Dieser Task schreibt keinen Anwendungscode.** Er hält die Entscheidung fest, und die beiden
folgenden setzen sie um. Ohne sie ist jede Liste geraten.

**Entschieden am 2026-09-11: dauerhaft, mit fester Schlüsselliste.** Der `ArrayAccess`-Zugriff
`$app['orm.em']` bleibt Teil der öffentlichen Framework-API. Ein Bestandsprojekt muss seine
Controller dafür nicht anfassen.

## Der Stand, auf dem die Entscheidung steht

Nachgemessen am 2026-09-11:

| | |
|---|---|
| Registrierte Schlüssel | 24, alle in `lib/contentfly/bootstrap.php` |
| Lesezugriffe | 134 im Framework, 25 in der Suite, 15 in der Vorlage |
| Häufigster | `$app['orm.em']`, 35-mal |
| Unbekannter Schlüssel | wirft `InvalidArgumentException` und nennt den Namen |
| Typisierter Zugang | gibt es nicht; `ArrayAccess` ist der einzige Weg |

**Zwei Dinge belasten die Entscheidung vor, und beide gehören in die Begründung:**

1. **Es gibt schon eine Zusage.** `an_project/docs/breaking-changes.md` führt seit Epic `009`
   unter *Was sich für ein Projekt nicht ändert*: „`$app['schlüssel']` — lesen und setzen.
   **Zugesichertes API**." Diese Entscheidung **bestätigt** sie, sie widerruft sie nicht — und
   genau das gehört gesagt, damit niemand später glaubt, die Frage sei nie gestellt worden.
2. **Das Framework benutzt die Bridge 134-mal selbst.** Eine Deprecation, die für Projekte
   gälte, müsste im Framework zuerst durchgezogen werden; sonst meldet der eigene Code die
   Warnung bei jedem Request. Das wäre ein eigener Umbau, kein Migrationsschritt.

## Acceptance criteria
- [ ] Die Entscheidung steht in `an_project/docs/architecture.md` unter *Key decisions*, mit Datum.
- [ ] Die verworfenen Alternativen stehen dabei: die befristete Deprecation und „dauerhaft, aber ohne Liste" — je mit dem Grund gegen sie.
- [ ] Der gemessene Stand steht dabei, nicht als Behauptung: 24 Schlüssel, 134 Lesezugriffe im Framework.
- [ ] Es steht dort, dass die Entscheidung die Zusage aus Epic `009` **bestätigt** und nicht neu erfindet.
- [ ] Eine Revisionsbedingung ist benannt — wann diese Entscheidung wieder aufzumachen wäre.

## Verification
Die beiden folgenden Tasks lassen sich aus dem geschriebenen Abschnitt ableiten, ohne dass die
Frage „dauerhaft oder befristet" erneut gestellt werden muss.
