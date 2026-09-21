---
id: 000-000-0072
title: Rechtestufe GROUP — /api/count und /api/query enden mit 500
status: review
depends_on: []
---

# Rechtestufe GROUP — /api/count und /api/query enden mit 500

## Context
**Gefunden in `000-000-0069` (2026-09-21), über die API bestätigt.** Für jeden Benutzer, dessen
Gruppe eine Entity mit `readable = GROUP` lesen darf, antworten `/api/count` und `/api/query` mit
**500** (`SQLSTATE[42000] … 1064`). `/api/list` und `/api/all` funktionieren mit derselben Stufe.

Die Ursache ist dieselbe Falle wie in `012-005-0003`: `groups` ist in MySQL 8 ein reserviertes Wort.
`Api::getCount()` (`FIND_IN_SET(?, groups)`) und `Api::getQuery()` (`FIND_IN_SET(?, groups)`) setzen
es als rohes SQL ohne Backticks ein; `getTree2()` und `getTranslations()` tun es richtig
(`` `groups` ``). Kein Test lief mit `GROUP` über diese beiden Routen.

## Acceptance criteria
- [x] `/api/count` und `/api/query` liefern mit `GROUP` 200 — mit und ohne Gruppe des Benutzers.
- [x] Das Ergebnis ist verengt: eigene und der eigenen Gruppe freigegebene Datensätze, keine anderen — geprüft in beide Richtungen, wie in `ReadPermissionApiTest`.
- [x] Eine Suche nach weiteren rohen `groups` ohne Backticks in `lib/` ist gemacht und festgehalten.

## Verification
Neue Tests in `ReadPermissionApiTest` bzw. `QueryApiTest`; vorher rot, danach grün.

## Ergebnis (2026-09-21)
**Zwei Backticks, zwei Tests.** `getCount()` und `getQuery()` schreiben jetzt `` `groups` ``.

- **Tests** in `ReadPermissionApiTest`: `/api/count` zählt für einen `GROUP`-Benutzer genau den
  eigenen und den der Gruppe freigegebenen Tag, nicht den fremden; `/api/query` liefert die beiden
  und nicht den dritten. **Gegenprobe:** ohne die Backticks beide rot (500).
- **„Ohne Gruppe" ist kein erreichbarer Fall.** Die Rechte eines Benutzers kommen über seine Gruppe;
  ohne Gruppe hat er keine `Permission`-Zeile und bekommt 403, bevor der `GROUP`-Zweig läuft. Die
  Zweige `if(!$group)` in `Api.php` sind für Nicht-Admins toter Code — vermerkt für `0074`.
- **Suche nach weiteren Stellen:** alle 21 Aufrufe von `FIND_IN_SET` in `lib/` und `custom/` durchgesehen. Nur
  diese zwei setzten `groups` roh und unqualifiziert ein. Qualifiziert (`t.groups`, `alias.groups`) ist
  es in MySQL erlaubt; die DQL-Stellen übersetzt Doctrine selbst.
- Registereintrag „Rechtestufe `GROUP`: `/api/count` und `/api/query` antworten wieder".

