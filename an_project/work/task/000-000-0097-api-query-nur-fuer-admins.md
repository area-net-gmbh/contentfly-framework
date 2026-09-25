---
id: 000-000-0097
title: /api/query nur noch für Admins
status: review
depends_on: []
---

# /api/query nur noch für Admins

## Context
**Gefunden bei `000-000-0076`.** `/api/query` baut eine Abfrage aus den Teilen des Requests und reicht
sie als SQL an die Datenbank. Nicht-Admins erreichten den Endpunkt, wenn ihre Gruppe
`apiQueryEnabled` trug. Die Rechteprüfung dahinter verengte nur die Entities, die im Schema stehen.

**Das ist keine Grenze.** Weil die Abfrageteile als SQL durchgehen, konnte eine solche Gruppe Daten
lesen, auf die ihre Rechte keinen Zugriff geben — auch Anmeldedaten. Eine Prüfung einzelner Teile
würde das nicht zuverlässig schliessen.

**Entschieden am 2026-09-25:** Der Endpunkt steht nur noch Admins offen. Ein Admin liest ohnehin
alles, also erweitert der Endpunkt für ihn nichts. Eine strukturierte Abfrage-API mit echter
Rechteprüfung wäre ein eigenes Feature.

## Acceptance criteria
- [x] `/api/query` antwortet jedem Nicht-Admin mit **403**, unabhängig von `apiQueryEnabled`.
- [x] Admins fragen unverändert ab; die Tests der Array-Syntax aus `0076` bleiben grün.
- [x] Die Rechte-Verengung in `getQuery()` ist gestrichen — sie ist für Admins wirkungslos und war für Nicht-Admins keine Grenze.
- [x] `apiQueryEnabled` bleibt als Spalte (kein Schema-Update), wird aber nicht mehr gelesen; der Docblock sagt es.
- [x] Registereintrag unter *API*, Leitfaden nachgezogen, DOC von `queryAction` nennt die Einschränkung.

## Verification
`QueryApiTest` und `ReadPermissionApiTest`, dazu die volle Suite und PHPStan.

## Ergebnis (2026-09-25)
**`/api/query` steht nur noch Admins offen.** Jeder andere Aufrufer bekommt 403
`contentfly_general_access_denied`, ob seine Gruppe `apiQueryEnabled` trägt oder nicht.

- **`Api::getQuery()`:** Die Sperre prüft nur noch `isAdmin`. Die Rechte-Verengung für `from` und
  Joins ist gestrichen — für einen Admin wirkungslos, für alle anderen nie eine Grenze. Die Auflösung
  von Entity- und Tabellennamen bleibt.
- **Status:** Die Ablehnung trug bisher keinen Status und antwortete deshalb mit **500**; jetzt 403.
- **`Group::$apiQueryEnabled`:** Die Spalte bleibt, wird aber nicht mehr gelesen. Kein Schema-Update,
  vorhandene Werte bleiben erhalten.
- **Tests:** `QueryApiTest` prüft die Sperre für vier Anfragen (mit und ohne `apiQueryEnabled`, einfach
  und mit Liste und Join), jeweils mit Leserecht auf die abgefragte Entity. Die Verengungs-Tests aus
  `0076` sind mit der Verengung entfallen; die Admin-Tests der Array-Syntax bleiben.
  `ReadPermissionApiTest` erwartet für den `GROUP`-Fall aus `0072` jetzt 403.
- **Gegenprobe:** Mit der alten Sperre, die `apiQueryEnabled` gelten lässt, werden genau die drei Fälle
  mit `enabled` rot.
- **Doku:** Registereintrag unter *API* mit der Abfrage, die betroffene Gruppen findet; Leitfaden
  140 Einträge, 46 unter *API*; DOC von `queryAction` und `STRUCTURE.md`.

**Geprüft:** volle Suite auf frischer Installation 859 grün, 3 übersprungen wie auf `master`;
PHPStan ohne Fehler; keine Deprecation im Server-Log.

**Bei Projekten kommt der Fix erst mit einem Release an.** Zusammen mit `0077` sind es zwei brechende
Änderungen — also `v2.4.0`, ein eigener Task.
