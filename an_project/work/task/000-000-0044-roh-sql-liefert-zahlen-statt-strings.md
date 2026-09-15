---
id: 000-000-0044
title: Roh-SQL liefert unter PHP 8.1+ Zahlen statt Strings
status: review
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
- [x] `$app['db']` und `$app['database']` setzen `PDO::ATTR_STRINGIFY_FETCHES`, mit Begründung im Code.
- [x] Ein Test hält fest, dass eine Zahl aus Roh-SQL über beide Verbindungen als String ankommt.
- [x] Die Hydrierung von Entities ist unverändert: Integer-, Boolean- und Decimal-Felder kommen mit ihren PHP-Typen an (Test oder Beleg aus der bestehenden Suite).
- [x] Die API-Antworten des Frameworks sind unverändert (volle Suite grün, ohne Anpassung von Erwartungen — oder jede Anpassung begründet).
- [x] `breaking-changes.md` beschreibt die PHP-8.1-Falle und dass das Framework sie abfängt; wie ein Projekt native Typen bekommt.
- [x] Am UFP-Probe-Backend liefert `cms-all-frontend` wieder `"0"`.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Test gegen beide Verbindungen. Aufzeichnung `cms-all-frontend` am UFP-Probe-Backend gegen
`recording-old-1` vergleichen.

## Ergebnis

**Beide Verbindungen liefern Zahlen aus Roh-SQL wieder als String.** `Classes\Database\ConnectionDefaults`
hält die Treiberoptionen an einer Stelle, mit Begründung: `PDO::ATTR_STRINGIFY_FETCHES`. Benutzt von
`$app['db']` (über `dbs.options`), `$app['database']` und der Verbindung von `appcms:install`.

**Test** `tests/Integration/Database/RawSqlTypesTest.php` (im Unterprozess wie `ContainerKeysTest`):
- `SELECT 1, 1.5, CAST(2.5 AS DOUBLE), NULL` über `database` und `db`, je direkt und als Prepared
  Statement mit Parameter → `'1'`, `'1.5'`, `'2.5'`, `null`.
- Hydrierung: `User::isActive` bleibt `bool`, `Base::views` (auf 7 gesetzt, danach zurück) bleibt `int`.
- **Gegenprobe** ohne das Attribut: der erste Test rot, der Hydrierungstest grün — genau die
  Zusicherung, dass Entities davon nicht abhängen.

**Eine Erwartung der Suite angepasst, begründet:** `TreeApiTest::testTree2PassesThroughTheRawDatabaseValues`
hielt `isActive` als Integer `1` fest. Charakterisiert wurde das in `008-001-0003` — auf PHP 8, also
das Verhalten von `pdo_mysql`, nicht der Vertrag von 1.x. Jetzt `'1'`, mit Vermerk im Test. Sonst
keine Erwartung geändert.

**Register:** `breaking-changes.md`, Abschnitt *Doctrine (Story `009-005`)*, neuer Eintrag „Zahlen
aus Roh-SQL bleiben Strings — auch unter PHP 8.1+", mit `/api/tree2` und dem Hinweis für eigene
Verbindungen. Kopf von `migration.md` auf 107 Einträge.

**Gemessen am UFP-Probe-Backend** (Container neu gestartet, Framework-Stand dieses Branches):
`cms-all-frontend` und `cms-view` gleichen der Aufzeichnung des alten Backends wieder — vorher
`body.data.0.isIntern: "0" -> 0`. Identisch 10 statt 8 Aufrufe; die übrigen Abweichungen sind die
bekannten der Phase 7 (Anmeldung) und F-2 (Dateipfad).

**Verifiziert:** volle Suite `Tests: 565, Assertions: 1823, Skipped: 3`, PHPStan `[OK] No errors`
(lokal mit `--memory-limit=1G`; der Standard von 128 MB reicht dem parallelen Worker hier nicht),
Deprecation-Gate 0.
