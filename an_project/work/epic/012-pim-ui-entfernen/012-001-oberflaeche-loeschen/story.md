---
id: 012-001-0000
title: Oberfläche und UI-Controller löschen
status: todo
depends_on: []
---

# Oberfläche und UI-Controller löschen

## Goal
Der große, risikoarme Kehraus: alles entfernen, was ausschließlich der Oberfläche dient und keine
API-Funktion trägt. Danach ist sichtbar, was vom Framework wirklich übrig bleibt — Grundlage für
die feineren Schnitte in 012-003 bis 012-006.

**Umfang**
- `lib/contentfly-ui/` bis auf `install.twig` löschen (31 MB — `app.twig`, `assets/`).
  **`install.twig` bleibt vorerst stehen**: Der `InstallController` ist bis 012-002 der einzige
  Installationsweg. Die Datei und der Rest des Verzeichnisses fallen dort.
- `lib/contentfly/Controller/UiController.php` (57 Zeilen) löschen.
- `lib/contentfly/Controller/ExportController.php` (443 Zeilen) löschen — der Excel-Export war
  Teil der Oberfläche.
- `lib/contentfly/Classes/Manager/UIManager.php` löschen.
- Die zugehörigen Routen-Registrierungen entfernen (`RouteManager`, Bootstrap, `custom/app.php`
  soweit betroffen).
- `ellumilel/php-excel-writer` wird nicht mehr referenziert — damit ist der harte PHP-8-Blocker
  aus Epic 006 gegenstandslos. **Twig bleibt vorerst**, weil `InstallController` und
  `BaseController` es noch nutzen; es fällt mit 012-002.
  Das physische Entfernen der Pakete aus `vendor/` gehört zu Epic 006 (dort entsteht das
  Manifest); hier verschwinden nur die Verwendungen im Code.

**Fertig, wenn**
- Kein Verweis auf `contentfly-ui`, `UiController`, `ExportController` oder `UIManager` mehr im
  Baum steht — auch nicht in Konfiguration, Routen oder Views.
- Die verbleibende Anwendung bootet und die API antwortet.
- `ellumilel/php-excel-writer` ist nirgends mehr referenziert; die einzigen verbliebenen
  Twig-Verwendungen sind `InstallController` und `BaseController`.

**Abgrenzung:** `InstallController` bleibt in dieser Story vorerst stehen (er wird in 012-002
ersetzt); `FileController` wird hier **nicht** angefasst.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 012-001-0001 — UiController und seine Routen entfernen
- [ ] 012-001-0002 — UIManager entfernen
- [ ] 012-001-0003 — ExportController samt Provider und Konfiguration entfernen
- [ ] 012-001-0004 — UI-Assets und app.twig löschen
