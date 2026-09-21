---
id: 000-000-0071
title: Die Kollision zweier php-parser-Versionen in der Suite beheben
status: review
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
- [x] Die Ursache ist bestätigt: welcher Test welche Kopie zuerst lädt.
- [x] Die Suite läuft mit jedem Seed durch, mit und ohne Coverage — zum Beispiel, weil der Rector-Test in einem eigenen Prozess läuft (`#[RunInSeparateProcess]`) oder Rector die Projekt-Version nutzt.
- [x] Der Coverage-Lauf im Runbook braucht kein `--order-by=default` mehr.

## Verification
Suite mehrfach mit wechselndem `--random-order-seed`, einmal davon mit PCOV.

## Ergebnis (2026-09-21)
**`DeadImportTest` schlägt Rector-Klassen in Rectors Classmap nach, statt sie zu laden — damit
bleibt Rectors Parser-Kopie ganz aus dem PHPUnit-Prozess.**

### Die Ursache, bestätigt
- **Nur ein Test** lädt Rector-Klassen in den PHPUnit-Prozess: `DeadImportTest`. Gemessen mit einer
  Sonde über jede Unit-Testdatei einzeln. `RectorRuleTest` startet Rector als eigenen Befehl.
- `class_exists('Rector\Rector\AbstractRector')` — ein Import in
  `Migration/RemovedAttributeFieldsRector` — löst Rectors `bootstrap.php` aus. Das bindet Rectors
  eigenen Composer-Autoloader **vorangestellt** ein, und Rector bringt `nikic/php-parser` **4**
  unpräfixiert mit; das Projekt hat **5.8**. Jede danach geladene `PhpParser\`-Klasse kam aus der
  alten Kopie.
- **Wann es knallt:** sobald `php-code-coverage` eine Quelldatei mit dem Parser des Projekts analysiert
  — also nur mit Coverage, nur mit **kaltem** Analyse-Cache (`.phpunit.cache/code-coverage`) und nur,
  wenn `DeadImportTest` vorher lief. Deshalb war es mal da, mal nicht.

### Warum nicht ein eigener Prozess
Zuerst versucht (`#[RunTestsInSeparateProcesses]`): Der Kindprozess sammelt ebenfalls Coverage und
stürzt mit kaltem Cache genauso ab (`Class_::verifyModifier()` undefiniert). Verworfen.

### Die Lösung
`DeadImportTest::rectorDeclares()` liest Rectors `autoload_classmap.php` — ein reines Array, das
nichts lädt — und beantwortet daraus, ob Rector (oder eine mitgebrachte Bibliothek wie
`Symplify\RuleDocGenerator`) den Namen kennt. Ein `Rector\`-Name erreicht `class_exists()` nicht mehr.

### Belegt
- **Gegenprobe mit erzwungener Reihenfolge und leerem Cache:** `DeadImportTest` vor `EnglishOnlyTest`,
  `InventoryToolTest`, `PackageManifestTest`, mit Coverage — mit dem Stand von `master` bricht PHPUnit
  ab, mit der Änderung läuft es durch.
- Sonde: Nach `DeadImportTest` ist keine Rector-Klasse und keine Parser-Klasse aus Rectors Kopie
  geladen.
- Unit-Suite mit Coverage unter drei Seeds, darunter die zwei, die vorher abgestürzt waren: grün.
- **Der volle Coverage-Lauf aus dem Runbook**, ohne ausgeklammerte Tests, ohne `--order-by`, mit
  kaltem Cache (Seed 1789997788): 740 Tests grün. Gesamt jetzt **70,5 % der Zeilen** (vorher 60,2 %).
- Runbook: der Hinweis auf `--order-by=default` ist entfernt.

**Hinweis für die Einrichtung von SonarQube (`0056`):** PCOV muss im Coverage-Job über die `php.ini`
geladen sein, nicht nur per `-d extension=` auf der Kommandozeile — sonst hat ein Kindprozess keinen
Treiber.

