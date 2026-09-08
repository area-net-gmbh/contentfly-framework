---
id: 006-002-0000
title: Root-Manifest und Lock für den Ist-Stack
status: todo
depends_on: [006-001-0000]
---

# Root-Manifest und Lock für den Ist-Stack

## Goal
Das Repo bekommt wieder ein `composer.json` — und zwar eines, das **den Ziel-Stack beschreibt,
nicht den Alt-Stand**. Damit ist Composer ab hier die Quelle der Wahrheit für Abhängigkeiten, und
Epic `009` (Kernel) und `010` (Entity-Layer) haben eine Basis, auf der sie überhaupt aufsetzen
können.

## Umfang

### Der Kern der Entscheidung
Ein Manifest kann nicht beides: Silex 2 pinnt `symfony/{http-kernel,http-foundation,routing,
event-dispatcher}` auf `~2.8|^3.0`, der Ziel-Stack verlangt Symfony 7.4. In **einem** Lock ist das
unauflösbar. Das Epic hat die Wahl bereits getroffen: Die Alt-Pakete werden nicht gerettet,
sondern ersetzt.

> **Konsequenz, die offen ausgesprochen gehört:** Mit dem Merge dieser Story **bootet der Baum
> nicht mehr**, bis Epic `009` den Kernel getauscht hat. `lib/contentfly/bootstrap.php` baut heute
> eine `Silex\Application`. Deshalb hängt Epic `006` an Epic `008` (Testnetz): Die
> Charakterisierungstests müssen den Vertrag festhalten, **bevor** die Grundlage wegfällt — sonst
> gibt es beim Kernel-Umbau nichts, woran sich „unverändert" messen ließe.

### Inhalt des Manifests
- **`config.platform.php` auf `8.5`** — die Vorgabe aus `an_project/docs/tech-stack.md`. Composer
  löst damit gegen die Zielplattform auf, nicht gegen die lokal installierte PHP-Version.
  *Konsequenz:* Auf einer Maschine mit PHP 8.3 braucht `composer install` entweder eine lokale 8.5
  oder `--ignore-platform-req=php`. Das gehört ins `an_project/docs/runbook.md`.
- **Symfony 7.4 LTS** — fixiert, nicht `^8`. Begründung in `an_project/docs/tech-stack.md` und
  `architecture.md` (*Key decisions*).
- **Doctrine auf einer releasten Version.** Der heutige Pin `doctrine/orm dev-bugfix-many2many`
  ist aufzulösen: Ein Dev-Branch lässt sich in keinem Upgrade-Pfad ausdrücken und macht jedes
  `composer audit` blind. Wenn der Branch einen echten Fehler behebt, ist zu prüfen, ob er in
  einem Release aufgegangen ist — und falls nicht, was an seine Stelle tritt.
- **Kein Silex, kein Pimple, keine Service-Provider** (`dflydev/doctrine-orm-service-provider`,
  `knplabs/console-service-provider`). Letzterer pinnt zusätzlich `doctrine/orm ~2.3` und blockiert
  damit ORM 3.
- **`ramsey/uuid` auf `^4`** — die 3.8.0 cappt auf PHP 7.
- **`ellumilel/php-excel-writer` und `twig/twig` entfallen ersatzlos** — Excel-Export und
  Oberfläche sind mit Epic `012` weg, beide sind im Code nachweislich unbenutzt.
- **Dev-Tools nach `require-dev`:** `phpstan/phpstan`, `rector/rector` (heute im
  Produktionsbaum), `phpunit/phpunit`, `mockery/mockery` (heute in `custom/`).
- **Die Zuordnung aus `006-001`** wird eingelöst: Was dort ins Root sortiert wurde, steht hier im
  `require`.

### Lock und Autoload
- `composer.lock` wird erzeugt und **committet** — er ist die Voraussetzung für reproduzierbare
  CI-Läufe und für `composer audit --locked` (Story `006-005`).
- Der `autoload`-Abschnitt bildet die heutigen Namespaces ab (`Areanet\PIM\` → `lib/contentfly`,
  `Custom\` → `custom`). Die Verdrahtung des zweiten Baums ist Sache von `006-004`.

## Offene Fragen
- **Bezugsweg des Frameworks.** Ob `lib/contentfly` künftig als Composer-Paket
  `areanet/contentfly` bezogen wird statt als kopierter Baum, entscheidet Epic `007`. Dieses
  Manifest greift dem **nicht vor** — es darf aber auch keine Paketgrenze verbauen. Konkret: Das
  Autoload-Layout wird so gewählt, dass `lib/contentfly` später ohne Umbau als eigenes Paket
  herausgelöst werden kann.
- **Ersatz für den Doctrine-Dev-Pin.** Welchen Fehler `dev-bugfix-many2many` behebt und ob er in
  einem Release aufgegangen ist, ist beim Umsetzen zu klären — nicht vorher zu raten.

## Fertig, wenn
- `composer.json` und `composer.lock` liegen im Repo-Root und beschreiben den Ziel-Stack.
- `config.platform.php` steht auf `8.5`; die Folge für lokale Installationen ist im
  `runbook.md` vermerkt.
- Kein Silex-, Pimple- oder Service-Provider-Paket ist mehr im `require`.
- `doctrine/orm` zeigt auf eine releaste Version; die Auflösung des Dev-Pins ist begründet
  festgehalten.
- `ellumilel/php-excel-writer` und `twig/twig` sind raus.
- Die vier Dev-Tools stehen in `require-dev`.
- `composer install` läuft auf einer PHP-8.5-Umgebung ohne `--ignore-platform-reqs` durch.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
