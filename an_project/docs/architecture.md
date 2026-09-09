<!-- PURPOSE: Architektur DIESES Projekts — Komponenten, Grenzen, Datenfluss und die Entscheidungen dahinter. -->

# Contentfly Framework — Architecture

<!-- Die Architektur des Frameworks selbst steht in
     .an_framework/docs/framework-architecture.md. Diese Datei beschreibt das Produkt. -->

## Overview

<!-- Was das System ist, in einem Absatz. Ein Diagramm, wenn es hilft. -->

## Components

<!-- Jeder größere Teil: was er tut, was er besitzt, mit wem er spricht. -->

## Boundaries & contracts

<!-- Die Schnittstellen, die stabil bleiben müssen. -->

## Key decisions

<!-- Entscheidung · erwogene Alternativen · warum diese. -->

### 2026-09-09 — Zwei Composer-Bäume, Root vor `custom/` (nicht ein Autoloader)

**Entscheidung.** Framework und Projekt behalten **je ein eigenes Manifest**.
`lib/contentfly/bootstrap.php` lädt `vendor/autoload.php` zuerst und
`custom/vendor/autoload.php` danach, falls es existiert. Bei einem PSR-4-Präfix, das beide
führen, **gewinnt der Root** — ab jetzt als Zusicherung, nicht als Nebenwirkung der
Ladereihenfolge. Bedingung dafür ist, dass sich die Bäume nicht überschneiden, und diese
Bedingung wird **geprüft** (`006-004-0003`).

**Erwogene Alternative: ein Autoloader.** `custom/` bekäme kein eigenes `vendor/` mehr;
projektspezifische Pakete stünden im Root-Manifest.

**Warum zwei Bäume.**

1. **Epic `007` braucht die Grenze.** Ein migrierendes Bestandsprojekt muss unterscheiden
   können, was es mitgeliefert bekommt und was es selbst deklariert. Ein gemeinsames Manifest
   löscht genau diese Linie — und mit ihr die Antwort auf die Frage, was beim nächsten
   Framework-Update überschrieben werden darf.
2. **`custom/` ist die Vorlage, nicht ein halbes Projekt** (`an_project/docs/technical.md`).
   Ein leerer, aber vorhandener Slot lehrt, wohin projektspezifische Pakete gehören. Ein
   fehlender lehrt nichts.
3. **Der Preis ist klein geworden.** Nach `006-001-0004` gehört **kein einziges** der neun
   `custom/`-Pakete dorthin; nach `006-003` wird der zweite Baum nicht mehr gebaut. Die
   Doppelung, gegen die die Regel schützt, existiert heute nicht — die Regel hält sie fern.

**Der Preis, ausgesprochen.** Eine Bedingung, die niemand prüft, ist keine Zusicherung. Genau
daran ist der alte Zustand gescheitert: `psr/log` lag in 1.1.3 und 3.0.2 gleichzeitig im
Prozess, dazu zwei `symfony/polyfill-*` in unvereinbaren Ständen und ein handkopiertes
`PHPMailer\PHPMailer\`, das in keiner `installed.json` stand. Jahrelang, ohne dass es jemandem
auffiel. Die Zusicherung dieser Entscheidung hängt deshalb **vollständig** an der Prüfung aus
`006-004-0003`; fällt die weg, fällt die Entscheidung mit.

**Wie ein neues Paket eingeordnet wird.** Nicht hier, sondern in
`tools/dependency-assignment.json` — dort steht die fünfstufige Regel (benutzt `lib/` es? ·
benutzt die ausgelieferte Vorlage es? · nur Projektcode? · nur Tests? · niemand?) samt der
Begründung für jedes bereits eingeordnete Paket. Eine zweite Fassung hier würde auseinanderlaufen.

**Revidieren, wenn:** Epic `007` entscheidet, das Framework als Composer-Paket auszuliefern
statt als kopierten `lib/`-Baum. Dann verschiebt sich die Grenze vom Verzeichnis auf die
Paketgrenze, und ein zweites Manifest im Projekt hat einen anderen Zuschnitt als heute.

### 2026-09-04 — Ziel-Kernel: Symfony 7.4 LTS (nicht Symfony 8.x)

**Entscheidung.** Der neue Kernel wird auf **Symfony 7.4 LTS** gebaut. Verbindlich
dazu: von Tag 1 deprecation-frei entwickeln, mit Deprecation-Log und PHPStan als CI-Gate.
Geplanter Nachfolger ist **Symfony 8.4 LTS** (erwartet Nov 2027) — als Constraint-Bump, nicht
als zweites Migrationsprojekt.

**Erwogene Alternative: Symfony 8.1** (im Sept 2026 die aktuelle stabile Zeile).

**Warum 7.4.**

1. Symfony 8.0 erschien am selben Tag wie 7.4 und hat **denselben Funktionsumfang** — der Major
   löscht nur deprecated Code und hebt das PHP-Minimum von 8.2 auf 8.4. Es gibt in 8.x kein
   Feature, das in 7.4 fehlt; ein Verzicht entsteht nicht.
2. **Support-Fenster passt zum realen Wartungsverhalten.** Silex ist seit 2018 EOL und läuft
   hier immer noch; Doctrine ORM hängt auf einem Dev-Branch. 7.4 trägt ohne Zutun bis Nov 2029.
   Die 8.x-Zeile verlangt alle 6 Monate ein Minor-Upgrade (8.1 → 8.2 → 8.3 → 8.4) — wer den
   Takt nicht mitgeht, sitzt ab Jan 2027 wieder ohne Security-Fixes da, also exakt in der
   Situation, aus der diese Migration herausführen soll.
3. **PHP-Entscheidung bleibt entkoppelt.** 7.4 verlangt nur PHP ≥ 8.2 und läuft auf 8.3 wie auf
   dem Zielstand 8.5. Solange Alt- und Neustand nebeneinander laufen, ist die PHP-Anhebung
   dadurch kein Vorab-Blocker. Symfony 8.x setzt PHP ≥ 8.4 in *jeder* Umgebung voraus — lokal
   läuft heute 8.3, und Bestandsprojekte bringen mit, was sie mitbringen.
4. **Der Migrationszeitraum selbst bleibt geschützt.** Mit 8.1 fiele mitten in den Kernel-Umbau
   (009) ein Pflicht-Upgrade auf 8.2, bevor das Testnetz aus 008 überall grün ist.

**Was die Entscheidung nicht beeinflusst.** Der eigentliche Aufwandstreiber ist versionsneutral:
Doctrine ORM 2 → 3 (Annotationen entfallen ersatzlos, alle Entities auf PHP-Attribute), das
Auflösen des Dev-Branch-Pins `doctrine/orm dev-bugfix-many2many` und der Ersatz des abandoned
`doctrine/annotations` — siehe Epic 010.

**Revidieren, wenn:** PHP ≥ 8.4 bei allen Bestandsprojekten gesetzt ist, das Versprechen „läuft
auf Standard-Providern" aus dem README entfällt **und** ein verbindliches Upgrade-Fenster alle
6 Monate eingeplant ist.
