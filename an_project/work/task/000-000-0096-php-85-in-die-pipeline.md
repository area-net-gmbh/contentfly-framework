---
id: 000-000-0096
title: "PHP 8.5 in die Pipeline: wirkungslose Aufrufe streichen, Spalte test: PHP 8.5"
status: done
depends_on: []
---

# PHP 8.5 in die Pipeline: wirkungslose Aufrufe streichen, Spalte test: PHP 8.5

## Context
**Die Folge von `000-000-0054`.** Auf PHP 8.5.11 bauen die Erweiterungen, der Lock installiert, das
Audit ist sauber und die Suite grün. Entschieden ist die Matrix: 8.5 kommt als **dritte Spalte**,
8.3 bleibt, solange das Manifest `^8.3` zusagt.

Heute wäre ein Job auf 8.5 rot — an Aufrufen, die seit Jahren nichts mehr tun:

| Wo | Aufruf | wirkungslos seit |
|---|---|---|
| `Classes/File/Processing/Image.php`, 7 Stellen | `imagedestroy()` — **das Gate schlägt hier an** | PHP 8.0 |
| `tests/` (9 Dateien) und `tools/migration/record-api.php`, 12 Stellen | `curl_close()` | PHP 8.0 |
| `tests/Unit/Kernel/RouteNamesTest.php:67` | `ReflectionProperty::setAccessible()` | PHP 8.1 |

Dazu, neu mit 8.5 und vom Gate nicht erfasst: `list($width, $height) = getimagesize(…)` in
`FileController.php` (zwei Stellen) warnt, wenn `getimagesize()` `false` liefert.

## Acceptance criteria
- [x] Die drei wirkungslosen Aufrufe sind gestrichen; das Verhalten unter 8.3 und 8.4 ist unverändert (Suite grün).
- [x] Die beiden `getimagesize()`-Stellen sind gegen `false` abgesichert; `$width`/`$height` bleiben `null` wie bisher.
- [x] Die Matrix von `test` ist `['8.3', '8.4', '8.5']`; der Job `test: PHP 8.5` ist grün, **Deprecation-Gate eingeschlossen**.
- [x] Ein Lauf ohne `restrictDeprecations` meldet auf 8.5 auch im Testprozess keine Deprecation.
- [x] `test: PHP 8.5` ist erforderlicher Check im Ruleset `master-schutz`; `git.md` nennt ihn.

## Verification
Der CI-Lauf des Pull Requests. Lokal wie in `0054`: der Pipeline-Job in `php:8.5-cli` nachgestellt.

## Ihre Schritte
Nach dem Merge: *Settings → Rulesets → master-schutz → Require status checks* → `test: PHP 8.5`
hinzufügen. Erst danach `git.md` als erledigt abhaken.

## Ergebnis (2026-09-25)
**`test: PHP 8.5` steht in der Matrix, blockierend; lokal nachgestellt ist der Job grün, Gate
eingeschlossen.** Offen sind der CI-Lauf des Pull Requests und der Eintrag im Ruleset.

### Was sich geändert hat
- **Gestrichen:** 7 × `imagedestroy()` in `Image.php`, 12 × `curl_close()` in `tests/` (9 Dateien) und
  `tools/migration/record-api.php`, 1 × `setAccessible()` in `RouteNamesTest`. Alle drei tun seit PHP
  8.0 bzw. 8.1 nichts mehr.
- **`getimagesize()` abgesichert** — an **drei** Stellen in `FileController`, nicht zwei: Die bei der
  ersten Upload-Variante (Z. 185) lief in `0054` nur nicht an, hat aber dasselbe Muster. `$width` und
  `$height` bleiben `null`, wenn die Datei kein Bild ist, wie bisher.
- **Matrix** `['8.3', '8.4', '8.5']`, mit Begründung im Kommentar von `pipeline.yml`.
- **Doku:** `deployment.md` (Gate-Tabelle, neuer Job, Deprecation-Gate auf drei Versionen),
  `technical.md`, `README.md`, `git.md` (sieben erforderliche Checks), Docblock von `TARGET_PLATFORM`,
  Bilanz von Epic `011`. **Nicht geändert:** `uebergabe-security.md` ist eine datierte Momentaufnahme
  (Stand 2026-09-14), `architecture.md` beschreibt an der Stelle, was Epic `009` eingelöst hat.

### Geprüft
| Lauf | Ergebnis |
|---|---|
| PHP 8.3, lokal, frische Installation | Suite 862 grün (3 übersprungen), 0 Deprecations, PHPStan ohne Fehler |
| PHP 8.5.11, Pipeline-Job in `php:8.5-cli` nachgestellt, `CI=true` | Suite 862 grün; **Deprecation-Gate grün: 0 Zeilen**; die Warnung `Cannot use bool as array` ist weg |
| derselbe Lauf **ohne** `restrict*` | **0 Deprecations** im Testprozess |

Im Server-Log bleiben zwei Arten Meldungen, beide unverändert und auf 8.3 genauso: die Notices, die
`getimagesize()` selbst für Nicht-Bilder ausgibt, und `foreach()` über `null` in `Api.php:302` (ein
Insert ohne `data`). Das Gate liest nur Deprecations.

### Befund, nicht Teil dieses Tasks
Der ungefilterte Lauf meldet **zwei Warnungen im Testprozess, die zwei hohle Tests verraten** — nicht
8.5-spezifisch, „Undefined array key" meldet PHP seit 8.0:
- `SchemaCacheApiTest::testWithTheCacheOffTheFileIsNotRead` liest `settings['label']`, einen Schlüssel,
  den das Schema nicht hat; die Prüfung vergleicht den Marker mit `null` und ist immer grün.
- `AuthApiTest::testDeactivatedUserGetsNoNewAccessJwt` liest `$login['refreshToken']`, das es dort nicht
  gibt; der Refresh bekommt `null`, und die 401 kommt vom fehlenden Token, nicht vom deaktivierten
  Benutzer.

### Abgeschlossen (2026-09-25)
- **CI:** `test: PHP 8.5` grün auf dem Pull Request #72 (`9fea926f`) und auf dem gemergten `master`
  (`9809370a`), zusammen mit den sechs übrigen Checks.
- **Ruleset `master-schutz`:** verlangt jetzt sieben Checks, `test: PHP 8.5` eingeschlossen — gelesen
  über `GET /repos/…/rules/branches/master`, nicht aus der Doku.
