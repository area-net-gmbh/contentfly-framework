---
id: 007-003-0001
title: Die Entscheidung festschreiben — der $app-Zugriff bleibt
status: done
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
- [x] Die Entscheidung steht in `an_project/docs/architecture.md` unter *Key decisions*, mit Datum.
- [x] Die verworfenen Alternativen stehen dabei: die befristete Deprecation und „dauerhaft, aber ohne Liste" — je mit dem Grund gegen sie.
- [x] Der gemessene Stand steht dabei, nicht als Behauptung: 24 Schlüssel, 134 Lesezugriffe im Framework.
- [x] Es steht dort, dass die Entscheidung die Zusage aus Epic `009` **bestätigt** und nicht neu erfindet.
- [x] Eine Revisionsbedingung ist benannt — wann diese Entscheidung wieder aufzumachen wäre.

## Verification
Die beiden folgenden Tasks lassen sich aus dem geschriebenen Abschnitt ableiten, ohne dass die
Frage „dauerhaft oder befristet" erneut gestellt werden muss.

## Ergebnis

**Die Entscheidung steht in `an_project/docs/architecture.md` unter *Key decisions*, datiert auf
den 2026-09-11.** Hier steht, was entschieden wurde und was beim Entscheiden zählte.

**Der `$app[...]`-Zugriff bleibt dauerhaft Teil der öffentlichen API, mit fester
Schlüsselliste.** Ein Bestandsprojekt muss seine Controller nicht anfassen — der grösste
Einzelposten, den dieses Epic ihm ersparen kann.

**Verworfen: die befristete Deprecation, und der Grund ist eine Zahl — 134.** So oft benutzt das
Framework die Bridge selbst. Eine Deprecation, die für Projekte gilt, müsste im Framework zuerst
durchgezogen werden; sonst meldet der eigene Code bei jedem Request die Warnung, und das
Deprecation-Gate aus `006-005` wäre ab dem ersten Tag rot. Das ist ein eigener Umbau, kein
Migrationsschritt.

**Der zweite Grund gegen sie wiegt schwerer als die Zahl:** Es gibt keinen benannten Nachfolger.
Eine Deprecation ohne Ersatz ist keine Migrationshilfe, sondern eine Drohung — sie verschiebt
Arbeit, statt sie zu ersparen.

**Verworfen: dauerhaft ohne Liste.** Das wäre die geringste Arbeit und liesse genau die Frage
offen, die die Story stellt. Ob `$app['schema']` morgen noch existiert oder ob es nur interne
Verdrahtung war, stünde nirgends. Eine Zusicherung ohne Gegenstand ist keine.

## Was die Entscheidung nicht ist

**Sie erfindet die Frage nicht neu, sie schliesst sie ab.** `breaking-changes.md` führt die
Bridge seit Epic `009` als „Zugesichertes API". Diese Entscheidung **bestätigt** das — und das
steht ausdrücklich dabei, damit niemand später glaubt, die Frage sei nie gestellt worden.

**Sie sichert nicht zu, dass `$app[...]` der einzige Weg bleibt.** Wer typisierten Zugang will,
kann ihn *daneben* stellen, ohne diese Entscheidung zu brechen. Die Revisionsbedingung ist
entsprechend gefasst: neu zu entscheiden, sobald ein typisierter Zugang gebaut ist und sich im
Framework durchgesetzt hat — dann gibt es den Nachfolger, der heute fehlt.

**Kein Anwendungscode geändert.** Das war der Zweck: Solange die Richtung nicht steht, ist jede
Liste geraten.
