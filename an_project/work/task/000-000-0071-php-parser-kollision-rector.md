---
id: 000-000-0071
title: Die Kollision zweier php-parser-Versionen in der Suite beheben
status: todo
depends_on: []
---

# Die Kollision zweier php-parser-Versionen in der Suite beheben

## Context
**Gefunden in `000-000-0067` (2026-09-21).** Rector bringt unter
`vendor/rector/rector/vendor/nikic/php-parser` eine **eigene, nicht umbenannte** Kopie von
`nikic/php-parser` mit — eine ältere Hauptversion als die des Projekts. Welche Klasse
`PhpParser\Node\Expr\List_` zuerst geladen wird, hängt von der Testreihenfolge ab, und
`phpunit.xml.dist` mischt sie zufällig (`executionOrder="random"`).

Beim Coverage-Lauf brach PHPUnit bei 17 % ab: `Undefined constant
PhpParser\Node\Expr\List_::KIND_LIST`. Mit `--order-by=default` lief er durch. Betroffen sind die
Tests, die einen Parser benutzen: `RectorRuleTest`, `InventoryToolTest`, `EnglishOnlyTest`,
`PackageManifestTest`. Ein reihenfolgeabhängiger Abbruch ist ein roter Lauf, den niemand erklären
kann.

## Acceptance criteria
- [ ] Die Ursache ist bestätigt: welcher Test welche Kopie zuerst lädt.
- [ ] Die Suite läuft mit jedem Seed durch, mit und ohne Coverage — zum Beispiel, weil der Rector-Test in einem eigenen Prozess läuft (`#[RunInSeparateProcess]`) oder Rector die Projekt-Version nutzt.
- [ ] Der Coverage-Lauf im Runbook braucht kein `--order-by=default` mehr.

## Verification
Suite mehrfach mit wechselndem `--random-order-seed`, einmal davon mit PCOV.
