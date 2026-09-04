---
id: 011-000-0000
title: Release der neuen Framework-Version
status: todo
depends_on: [007-000-0000, 009-000-0000, 010-000-0000]
---

# Release der neuen Framework-Version

## Goal
Die neue Contentfly-Version ist veröffentlicht, dokumentiert und für Bestandsprojekte beziehbar.
Kein Cutover im Produktivsinn — aus diesem Repo wird nichts ausgerollt (siehe *Scope* in
`an_project/project-description.md`); „fertig" heißt hier: eine Version, die ein Projekt
tatsächlich einsetzen und auf die es migrieren kann.

## Erfolgskriterien
- **Silex ist weg** — kein `silex/silex`, kein `pimple`, keine Symfony-2/3-Komponente mehr im
  Baum. Prüfbar, nicht behauptet.
- **`composer audit --locked` sauber** unter der Zielplattform PHP 8.5.
- **Version vergeben und Bruchstellen benannt:** eine neue Hauptversion mit vollständiger Liste
  der Breaking Changes; `version.php` und die Framework-Metadaten stimmen überein.
- **Beziehbar:** Der in 007 entschiedene Bezugsweg (Composer-Paket statt kopiertem `lib/`-Baum)
  ist umgesetzt und einmal aus Projektsicht durchgespielt — frischer Checkout, Installation,
  lauffähig.
- **Vorlage stimmt:** `custom/` zeigt den Zielzustand — Example-Controller, -Entity, -Service und
  -Command laufen auf der neuen Version und taugen wieder als Referenz.
- **Doku nachgezogen:** `README.md`, `an_project/docs/runbook.md`, `technical.md`, `dev-guide.md`
  und der Migrationsleitfaden aus 007 beschreiben den neuen Stand, nicht den alten.
- **Upgrade-Pfad festgehalten:** Symfony 8.4 LTS (erwartet Nov 2027) als geplanter nächster
  Schritt, abgesichert durch das CI-Gate „0 Deprecations" aus 009.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
