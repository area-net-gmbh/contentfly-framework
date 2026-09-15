---
id: 000-000-0046
title: Spalte ohne Contentfly-Typ zerstört im Debug-Modus die Antwort
status: todo
depends_on: []
---

# Spalte ohne Contentfly-Typ zerstört im Debug-Modus die Antwort

## Context
**Gefunden bei `007-005-0004`** am Bestandsprojekt UFP (Befund F-5).

Seit `000-000-0017` meldet `Api::getExtendedSchema()` eine Spalte, zu der kein Contentfly-Typ
passt, mit `trigger_error(..., E_USER_WARNING)` — damit sie nicht still aus dem Schema fällt. UFP hat
eine solche Spalte: `AI\VectorDocument::embedding` (`blob`).

**Mit `APP_DEBUG` setzt der Bootstrap `display_errors=1`, und die Warnung steht als HTML vor dem
JSON.** Gemessen: `POST /oauth2/user` antwortet `200` mit `text/html`, erste Zeile
`<b>Warning</b>: No Contentfly type matches AI\VectorDocument::embedding (column type "blob")`.
Das trifft jeden Aufruf, der das Schema baut — auf Entwicklungs- und Staging-Instanzen, die mit
Debug laufen. Ohne Debug landet die Warnung nur im Log.

Offen, nicht entschieden: ob `blob` einen Typ bekommt, ob die Meldung ins Log statt in die Ausgabe
gehört, oder ob ein Projekt eine Spalte ausdrücklich aus dem Schema nehmen kann.

## Acceptance criteria
- [ ] Entscheidung dokumentiert (Typ für `blob`, Meldungsweg oder Ausschluss).
- [ ] Eine Spalte ohne passenden Typ zerstört mit `APP_DEBUG` keine JSON-Antwort mehr — und bleibt trotzdem sichtbar gemeldet.
- [ ] Test.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Test mit einer Entity mit `blob`-Spalte unter `display_errors=1`. Am UFP-Probe-Backend:
`POST /oauth2/user` antwortet `application/json`.
