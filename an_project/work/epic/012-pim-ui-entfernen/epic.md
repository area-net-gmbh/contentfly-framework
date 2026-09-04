---
id: 012-000-0000
title: PIM-CMS-Oberfläche ersatzlos entfernen
status: todo
depends_on: []
---

# PIM-CMS-Oberfläche ersatzlos entfernen

## Goal
Contentfly wird auf **reine Datenhaltung plus Core-Funktionen** reduziert. Keine Admin-UI, keine
PIM-Oberfläche, kein Ersatz. Was bleibt, ist die API: Datenmodell, Sync-Schnittstelle,
Authentifizierung, Dateiablage, Console.

Dieses Epic steht **am Anfang**, nicht am Ende: Jede Zeile, die hier verschwindet, muss weder
getestet (008) noch auf Symfony portiert (009) noch migriert (010) werden. Zuerst wegwerfen,
dann umbauen.

## Erfolgskriterien
- **`lib/contentfly-ui` ist gelöscht** (31 MB Twig-Templates und Assets — `app.twig`,
  `install.twig`, das gesamte `assets/`-Verzeichnis).
- **UI-Controller entfernt:** `UiController` und `InstallController`. Für den Installer wird
  vorher entschieden, ob er als **Console-Command** weiterlebt — ein Framework ohne
  Installationsweg ist kein Fortschritt. `ExportController` (Excel-Export) fällt mit der
  Oberfläche, womit sich auch der PHP-8-untaugliche `ellumilel/php-excel-writer` erledigt (006).
- **`UIManager` entfernt**; `TypeManager` behält nur seine Daten-Belange (Feldtypen, Casting,
  Validierung) und verliert die Formular-Belange. `PluginManager` wird auf UI-Registrierungen
  geprüft.
- **Sessionbasierte Admin-Auth entfernt.** Damit fällt der Session-Bootstrap in `Auth::init()`
  weg; die API authentifiziert ausschließlich token-/JWT-basiert. Nebenwirkung: Der
  PHP-Session-Write-Lock, der konkurrierende API-Aufrufe serialisiert, verschwindet mit.
- **Twig fliegt aus dem Abhängigkeitsbaum.**
- **`FileController` sauber getrennt:** Der API-Teil (Upload/Download für Sync und Apps) bleibt,
  die Admin-Datei-Oberfläche geht. Beides steckt heute in einer Datei — hier wird geschnitten,
  nicht pauschal gelöscht.
- **Die `@PIM`-Annotationen sind entrümpelt** (siehe unten) — der Teil, der ausschließlich
  Formulare beschrieben hat, ist weg.

## Der eigentliche Umfang: das Annotationssystem

Das `@PIM`-System ist überwiegend **UI-Metadatensystem**, nicht Datenmodell. Von 15
Annotationsklassen in `Classes/Annotations/` beschreiben 12 reine Formular-Widgets — `Rte` trägt
eine TinyMCE-Toolbar-Zeile, dazu `Checkbox`, `Radio`, `Select`, `Textarea`, `MatrixChooser`,
`Datetime`, `Time`, `Password`, `EntitySelector`, `Virtualjoin`.

Auch `Config` mischt beides und muss **aufgeteilt** werden:

| Bleibt (Daten/API) | Fällt (UI) |
|---|---|
| `excludeFromSync` — steuert die Sync-API (`Classes/Api.php:755`) | `viewMode`, `showInList`, `listShorten`, `hide` |
| `encoded` — steuert die Verschlüsselung (`StringType`, `TextareaType`) | `label`, `labelProperty`, `tab`, `tabs`, `sort` |
| `isFilterable` — API-Filter (`Classes/Type.php:117`) | `isDatalist`, `isSidebar`, `lines`, `accept` |
| `unique`, `type`, `i18n_universal`, `sortBy`/`sortOrder`/`sortRestrictTo` | `readonly` (prüfen: UI-Hinweis oder API-Schutz?) |

**Das ist der größte Einzeleingriff für Bestandsprojekte.** Diese Annotationen stehen in *deren*
Entities, nicht nur hier. **Entschieden am 2026-09-04: hart entfernen** — keine Duldungsphase,
keine wirkungslos tolerierten Restfelder. Epic 007 liefert dazu eine Rector-Regel, mit der ein
Projekt sein eigenes `Entity/`-Verzeichnis bereinigt.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [x] 012-001-0000 — Oberfläche und UI-Controller löschen
- [x] 012-002-0000 — Installer als Console-Command
- [x] 012-003-0000 — FileController schneiden — API behalten, Datei-UI entfernen
- [x] 012-004-0000 — Sessionbasierte Admin-Auth entfernen
- [ ] 012-005-0000 — @PIM-Annotationen entrümpeln
- [ ] 012-006-0000 — TypeManager und PluginManager von UI-Belangen befreien
