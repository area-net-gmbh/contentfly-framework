---
id: 000-000-0096
title: "PHP 8.5 in die Pipeline: wirkungslose Aufrufe streichen, Spalte test: PHP 8.5"
status: todo
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
- [ ] Die drei wirkungslosen Aufrufe sind gestrichen; das Verhalten unter 8.3 und 8.4 ist unverändert (Suite grün).
- [ ] Die beiden `getimagesize()`-Stellen sind gegen `false` abgesichert; `$width`/`$height` bleiben `null` wie bisher.
- [ ] Die Matrix von `test` ist `['8.3', '8.4', '8.5']`; der Job `test: PHP 8.5` ist grün, **Deprecation-Gate eingeschlossen**.
- [ ] Ein Lauf ohne `restrictDeprecations` meldet auf 8.5 auch im Testprozess keine Deprecation.
- [ ] `test: PHP 8.5` ist erforderlicher Check im Ruleset `master-schutz`; `git.md` nennt ihn.

## Verification
Der CI-Lauf des Pull Requests. Lokal wie in `0054`: der Pipeline-Job in `php:8.5-cli` nachgestellt.

## Ihre Schritte
Nach dem Merge: *Settings → Rulesets → master-schutz → Require status checks* → `test: PHP 8.5`
hinzufügen. Erst danach `git.md` als erledigt abhaken.
