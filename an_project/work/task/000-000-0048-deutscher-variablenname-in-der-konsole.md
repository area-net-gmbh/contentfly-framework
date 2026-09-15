---
id: 000-000-0048
title: Deutscher Variablenname in bin/console.php, den der Sprachwächter nicht sieht
status: done
depends_on: []
---

# Deutscher Variablenname in bin/console.php, den der Sprachwächter nicht sieht

## Context
**Gefunden bei `007-005-0003`** (Nebenbefund), als die Einstiegspunkte der Vorlage ins Bestandsprojekt UFP
kopiert wurden: `bin/console.php` benennt die DBAL-Verbindung `$verbindung` (Zeilen 33, 63, 64). Jedes
Projekt, das der Vorlage folgt, übernimmt den Namen.

Epic `014` hat den Baum auf Englisch gebracht, und `EnglishOnlyTest` prüft `bin/`. Er vergleicht aber
gegen eine Liste deutscher **Funktionswörter** („der“, „und“, „für“) — ein deutscher Bezeichner wie
`$verbindung` fällt durch.

## Acceptance criteria
- [x] `bin/console.php` benennt die Variable englisch (`$connection`).
- [x] Geklärt und festgehalten, ob der Sprachwächter deutsche Bezeichner erkennen soll; wenn ja, mit Test, der `$verbindung` gefunden hätte.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
`git grep -n verbindung -- bin lib custom` leer. `EnglishOnlyTest` grün.

## Ergebnis

**Entschieden am 2026-09-15:** Der Wächter prüft auch deutsche Fachwörter in Bezeichnern.

**`EnglishOnlyTest`** kennt 22 weitere Stämme — Substantive und Verbstämme, mit denen Code Dinge benennt
(`verbindung`, `anfrage`, `antwort`, `ergebnis`, `aktualisier`, `loesch`, `speicher`, …), mit Begründung
an der Liste. Der Detektor-Test hält die Zeile aus `bin/console.php` fest, die bis hierhin durchkam.

**Die Erweiterung hat eine zweite Stelle gefunden:** `ApiController::multiupdateAction()` benannte die
Verbindung `$verbindung` und die Liste der Ergebnisse `$aktualisiert`. Beide umbenannt (`$connection`,
`$updated`), ebenso die drei Stellen in `bin/console.php`. Fehltreffer im übrigen Baum: keine.

**Gegenprobe:** Mit dem alten `bin/console.php` meldet der Wächter alle drei Zeilen.

`git grep verbindung` findet in `bin`, `lib`, `custom` nichts mehr; übrig ist ein deutscher Kommentar in
`tools/ci/install-php-extensions.sh`, ausserhalb der Pfade, die Epic `014` übersetzt hat.

**Verifiziert:** volle Suite `Tests: 593, Assertions: 1895, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.
