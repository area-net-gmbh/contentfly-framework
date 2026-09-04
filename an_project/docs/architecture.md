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
