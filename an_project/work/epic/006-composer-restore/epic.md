---
id: 006-000-0000
title: Composer-Wiederherstellung und Dependency-Konsolidierung
status: in-progress
depends_on: [008-000-0000]
---

# Composer-Wiederherstellung und Dependency-Konsolidierung

## Goal
Abhängigkeiten werden wieder über Composer verwaltet statt über einen committeten `vendor/`-Baum —
mit PHP 8.5 als Constraint-Basis. Vorbedingung für den Kernel-Umbau (009) und den Entity-Layer
(010): ohne Root-Manifest gibt es weder einen Upgrade-Pfad für Doctrine noch ein
aussagekräftiges `composer audit`.

**Ausgangslage.** Der Root-`vendor/` (39 Pakete) hat kein `composer.json` — er wurde als
Notlösung eingefroren und in Git gelegt, um Contentfly ohne Ausfallzeit auf PHP 8.x
weiterzubetreiben. `custom/vendor` (48 Pakete) hat ein Manifest, trägt aber Kern-Infrastruktur
mit. Beide Autoloader werden im selben Prozess geladen
(`lib/contentfly/bootstrap.php:8-10`), wodurch Pakete doppelt und in inkompatiblen Majors
vorliegen. Details: `an_project/docs/technical.md`.

**Das Manifest beschreibt den Ist-Stack, nicht den Ziel-Stack. Entschieden am 2026-09-08,
gegen die ursprüngliche Fassung dieses Epics.**

Der ursprüngliche Zuschnitt sah vor, das Root-Manifest gleich für den Ziel-Stack zu schreiben
(PHP 8.5, Symfony 7.4, kein Silex). Das ist nicht durchführbar, ohne die Grundlage zu
zerstören, auf der die Folge-Epics abgenommen werden:

- `silex/silex` pinnt `symfony/{http-kernel,http-foundation,routing,event-dispatcher}` auf
  `~2.8|^3.0`, und **26 Dateien** hängen an Silex — `bootstrap.php`, `bootstrap-web.php` und
  sämtliche Controller-Provider. Silex zu ersetzen ist Epic `009`, also *nach* diesem hier.
- Ein Root-Manifest mit Symfony 7.4 machte Silex unauflösbar. Die Anwendung liefe zwischen
  `006` und `009` nicht — und mit ihr wäre die gesamte Testsuite rot. Genau jene 238 Tests,
  die Epic `008` als **Abnahmegrundlage** für `009` festgeschrieben hat
  (`an_project/docs/technical.md`). `009` hätte dann keine grüne Ausgangsbasis, gegen die es
  sich messen könnte.

Der ursprüngliche Text ist älter als das Testnetz; sein Argument („an diesem Repo hängt kein
Produktivbetrieb") trägt für `vendor/` in Git, beantwortet aber nicht, wovon `009` seine
Abnahme bezieht.

**Deshalb:** `006` liefert Manifest, Lock und Build für den **heutigen** Stack — Silex 2 und
die Symfony-3.4-Komponenten bleiben, `config.platform.php` steht auf **8.3**. `vendor/`
verschwindet trotzdem aus Git, Composer wird die Quelle der Wahrheit, und `composer audit`
läuft. Die Suite bleibt über den ganzen Weg grün. Den Sprung auf PHP 8.5 und Symfony 7.4 macht
`009`, wo der Code ohnehin umgebaut wird — dort ist es ein Constraint-Bump im selben Manifest
statt eines Neubaus.

**Auflösbar ist der Ist-Stack**, weil Silex und die Symfony-3.4-Komponenten nach oben offene
`php`-Constraints haben. Zwei Pakete müssen punktuell angehoben werden, weil sie auf PHP 7
cappen und **benutzt** werden: `ramsey/uuid` (3.8.0 → `^4`) und `doctrine/orm` (Dev-Branch-Pin
→ ein Release). Zwei weitere cappen ebenfalls, entfallen aber ersatzlos, weil Epic `012` ihre
Konsumenten gelöscht hat: `ellumilel/php-excel-writer` und `twig/twig`.

Diese beiden Upgrades sind der erste echte Nutzen des Testnetzes: Ein Doctrine-Wechsel vom
Dev-Branch auf ein Release ist die Sorte Änderung, die man ohne Absicherung nicht wagt.

## Erfolgskriterien
- **Root-`composer.json` + `composer.lock`** für den **Ist-Stack**: `config.platform.php` auf
  **8.3**, Silex 2 und die Symfony-3.4-Komponenten bleiben vorerst, Doctrine auf einer
  **releasten** Version (der Dev-Branch-Pin `doctrine/orm dev-bugfix-many2many` ist aufgelöst —
  er lässt sich in keinem Upgrade-Pfad ausdrücken), `ramsey/uuid` auf `^4`.
  **Die Suite bleibt dabei grün** — sie ist das Abnahmekriterium auch für diese Story.
  Der Sprung auf PHP 8.5, Symfony 7.4 und die Entfernung von Silex/Pimple gehört zu `009`.
- **`vendor/` ist aus Git entfernt.** Composer ist die Quelle; wo ein Zielsystem kein Composer
  hat, baut die Pipeline den Baum ins Artefakt
  (`composer install --no-dev --optimize-autoloader`). Siehe `an_project/docs/deployment.md`.
- **Bestandsprojekte im Blick:** Das Root-Manifest ist zugleich die Vorlage dafür, wie ein
  migrierendes Projekt seine Abhängigkeiten künftig deklariert. Ob das Framework dabei als
  Composer-Paket bezogen wird statt als kopierter `lib/`-Baum, entscheidet 007 — 006 darf dem
  nicht im Weg stehen (Autoload-Layout, Paketgrenzen).
- **`custom/vendor` ist initial leer** — ein Slot für projektbezogene Pakete. `custom/composer.json`
  enthält nur noch, was wirklich projektspezifisch ist (z. B. `stripe/stripe-php`).
- **Doppelte Abhängigkeiten aufgelöst**, zuerst `psr/log` (1.1.3 gegen 3.0.2) und die
  `symfony/polyfill-*` (1.14 gegen 1.37/1.38).
- **Grenzfälle eingeordnet:** `firebase/php-jwt`, `vlucas/phpdotenv`, `onelogin/php-saml`,
  `sentry/sentry` — Root (Framework-Sache) oder `custom/` (Projekt-Sache). Entscheidung
  begründet festhalten, sie prägt die Vorlage für alle Folgeprojekte.
- **Dev-Tools nach `require-dev`:** `phpstan`, `rector` (heute im Root-Produktionsbaum),
  `phpunit`, `mockery` (heute in `custom/vendor`).
- **`ellumilel/php-excel-writer` und `twig/twig` sind raus** — der Excel-Export und die
  Oberfläche entfallen mit 012. Kein Ersatz nötig; der PHP-8-Blocker löst sich damit auf.
- **`composer audit --locked` läuft in der CI** und ist auf dem neuen Manifest sauber.
- **Autoloader-Situation geklärt:** ein definierter Zwei-Baum-Bootstrap (Root zuerst, `custom/`
  ergänzend, ohne überlappende Pakete) oder die Zusammenführung auf einen Autoloader.

## Abgrenzung
Kein Code-Umbau am Kernel (009) und keine Entity-Migration (010). Dieses Epic liefert Manifest,
Lock, Build-Pipeline und einen entrümpelten Abhängigkeitsbaum — darauf bauen die beiden anderen.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [ ] 006-001-0000 — Abhängigkeiten inventarisieren und zuordnen
- [ ] 006-002-0000 — Root-Manifest und Lock für den Ist-Stack
- [ ] 006-003-0000 — vendor/ aus Git lösen und den Build nachziehen
- [ ] 006-004-0000 — Autoloader klären und custom/ entrümpeln
- [ ] 006-005-0000 — composer audit als CI-Gate
