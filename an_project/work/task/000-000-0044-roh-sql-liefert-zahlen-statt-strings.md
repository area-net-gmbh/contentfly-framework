---
id: 000-000-0044
title: Roh-SQL liefert unter PHP 8.1+ Zahlen statt Strings
status: todo
depends_on: []
---

# Roh-SQL liefert unter PHP 8.1+ Zahlen statt Strings

## Context
**Gefunden bei `007-005-0004`** am Bestandsprojekt UFP (Befund F-6).

Seit PHP 8.1 gibt `pdo_mysql` bei emulierten Prepared Statements — der Vorgabe, die DBAL nutzt —
Ganz- und Fliesskommazahlen als `int`/`float` zurück statt als String. Contentfly 1.x lief auf PHP
7.4 und lieferte `"0"`, Contentfly 2 liefert `0`. Das ist keine Änderung im Code des Frameworks,
aber eine im Draht: Jede Antwort, die ein Projekt aus Roh-SQL (`$app['database']`) baut, ändert
ihre JSON-Typen.

**Gemessen an derselben Zeile** `modules_cms_page`: PHP 7.4 `{"isIntern":"0","one":"1"}`, PHP 8.3
`{"isIntern":0,"one":1}`, PHP 8.3 mit `PDO::ATTR_STRINGIFY_FETCHES` `{"isIntern":"0","one":"1"}`.
Im Aufzeichnungsvergleich von UFP sichtbar als `body.data.0.isIntern: "0" -> 0`.

**Die Folge ist still:** Das UFP-Frontend vergleicht an rund 50 Stellen strikt mit `'1'`/`'0'`
(`item.userHasUpvoted === '1'`, `user.isAccepted === '1'`, `q.optional === '1'`). Keiner dieser
Vergleiche wirft — sie werden nur falsch.

**Entschieden am 2026-09-15:** Das Framework stellt das Verhalten von 1.x wieder her, für beide
Verbindungen. Ein Projekt, das native Typen will, schaltet es selbst ab.

## Acceptance criteria
- [ ] `$app['db']` und `$app['database']` setzen `PDO::ATTR_STRINGIFY_FETCHES`, mit Begründung im Code.
- [ ] Ein Test hält fest, dass eine Zahl aus Roh-SQL über beide Verbindungen als String ankommt.
- [ ] Die Hydrierung von Entities ist unverändert: Integer-, Boolean- und Decimal-Felder kommen mit ihren PHP-Typen an (Test oder Beleg aus der bestehenden Suite).
- [ ] Die API-Antworten des Frameworks sind unverändert (volle Suite grün, ohne Anpassung von Erwartungen — oder jede Anpassung begründet).
- [ ] `breaking-changes.md` beschreibt die PHP-8.1-Falle und dass das Framework sie abfängt; wie ein Projekt native Typen bekommt.
- [ ] Am UFP-Probe-Backend liefert `cms-all-frontend` wieder `"0"`.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Test gegen beide Verbindungen. Aufzeichnung `cms-all-frontend` am UFP-Probe-Backend gegen
`recording-old-1` vergleichen.
