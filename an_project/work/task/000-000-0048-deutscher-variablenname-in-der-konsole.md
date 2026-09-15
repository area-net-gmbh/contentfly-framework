---
id: 000-000-0048
title: Deutscher Variablenname in bin/console.php, den der Sprachwächter nicht sieht
status: todo
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
- [ ] `bin/console.php` benennt die Variable englisch (`$connection`).
- [ ] Geklärt und festgehalten, ob der Sprachwächter deutsche Bezeichner erkennen soll; wenn ja, mit Test, der `$verbindung` gefunden hätte.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
`git grep -n verbindung -- bin lib custom` leer. `EnglishOnlyTest` grün.
