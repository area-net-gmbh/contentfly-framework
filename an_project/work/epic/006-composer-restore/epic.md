---
id: 006-000-0000
title: Composer-Wiederherstellung und Dependency-Konsolidierung
status: todo
depends_on: []
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

**Zweigleisig, und das ist der Kern des Zuschnitts.** Mit PHP 8.5 als Constraint-Basis lässt
sich der Alt-Baum nicht sanieren:
- `ellumilel/php-excel-writer` verlangt `php: ^5.4|^7.0` — unter PHP 8.5 **nicht installierbar**.
- `silex/silex` pinnt `symfony/{http-kernel,http-foundation,routing,event-dispatcher}` auf
  `~2.8|^3.0`; Symfony 3.4 ist seit Nov 2020 EOL und nie für PHP 8 freigegeben. Composer
  installiert es trotzdem, weil die `php`-Constraints nach oben offen sind — genau die Lücke, die
  das Notfall-Update hinterlassen hat.
- `dflydev/doctrine-orm-service-provider` pinnt `doctrine/orm ~2.3` und blockiert damit ORM 3.

Deshalb beschreibt das neue Root-Manifest **den Ziel-Stack**, nicht den Alt-Stand. Da an diesem
Repo kein Produktivbetrieb hängt (siehe *Scope* in `an_project/project-description.md`), kann
`vendor/` sofort aus Git verschwinden — es muss nicht bis zum Abschalten von Silex (009) warten.
Die Alt-Pakete werden nicht gerettet, sondern ersetzt.

## Erfolgskriterien
- **Root-`composer.json` + `composer.lock`** für den Ziel-Stack: `config.platform.php` auf 8.5,
  Symfony 7.4 LTS, Doctrine auf einer **releasten** Version (der Dev-Branch-Pin
  `doctrine/orm dev-bugfix-many2many` ist aufgelöst — er lässt sich in keinem Upgrade-Pfad
  ausdrücken), keine Silex-/Pimple-/Service-Provider-Pakete mehr.
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
