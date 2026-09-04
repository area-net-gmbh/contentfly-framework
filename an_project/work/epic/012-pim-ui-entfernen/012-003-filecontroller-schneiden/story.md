---
id: 012-003-0000
title: FileController schneiden — API behalten, Datei-UI entfernen
status: todo
depends_on: [012-001-0000]
---

# FileController schneiden — API behalten, Datei-UI entfernen

## Goal
Der `FileController` (482 Zeilen) trägt beides: die **API-Dateifunktionen**, die Sync und Apps
brauchen (Upload, Download, Auslieferung), und die **Admin-Dateiverwaltung** der gestrichenen
Oberfläche. Hier wird geschnitten, nicht pauschal gelöscht — ein Fehlgriff nimmt Bestandsprojekten
eine Kernfunktion weg.

**Umfang**
- Jede Action des Controllers einzeln zuordnen: API-Funktion oder UI-Funktion. Zuordnung
  festhalten, nicht nur im Kopf treffen.
- Was an der UI hängt (Ordnerverwaltung, Thumbnails/Vorschauen nur für die Maske,
  Massenoperationen der Admin-Ansicht), entfernen.
- Was an der API hängt, unverändert lassen — inklusive der Entities `File` und `Folder` und der
  zugehörigen Traits. **Die Datenhaltung bleibt**, auch wenn die Verwaltungsoberfläche geht.
- `Classes/File/` auf UI-Belange prüfen.

**Fertig, wenn**
- Upload und Download über die API funktionieren wie vorher, nachgewiesen durch Tests.
- Keine Route und keine Methode mehr existiert, die nur die gelöschte Maske bedient hat.
- Die Zuordnungsliste (Action → API oder UI → behalten oder gelöscht) liegt vor und wandert in
  den Migrationsleitfaden (Epic 007) — Bestandsprojekte müssen wissen, was ihnen fehlt.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 012-003-0001 — Actions des FileControllers zuordnen: API oder UI
- [ ] 012-003-0002 — Admin-Dateiverwaltung entfernen
- [ ] 012-003-0003 — API-Dateifunktionen mit Tests absichern
