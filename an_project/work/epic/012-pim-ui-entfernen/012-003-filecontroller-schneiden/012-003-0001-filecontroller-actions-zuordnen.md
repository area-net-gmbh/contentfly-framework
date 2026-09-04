---
id: 012-003-0001
title: Actions des FileControllers zuordnen: API oder UI
status: review
depends_on: []
---

# Actions des FileControllers zuordnen: API oder UI

## Context
Der `FileController` (482 Zeilen) trägt API-Dateifunktionen und Admin-Dateiverwaltung in einer Datei. Ohne saubere Zuordnung wird beim Löschen Sync-Funktion mit entfernt.

## Acceptance criteria
- [x] Jede Action ist als API-Funktion oder UI-Funktion eingestuft, mit Begründung.
- [x] Für die API-Funktionen ist belegt, wer sie aufruft (Sync, Apps, `custom/`-Vorlage).
- [x] `lib/contentfly/Classes/File/` ist in dieselbe Zuordnung einbezogen.
- [x] Die Liste ist festgehalten und für den Migrationsleitfaden (Epic 007) verwendbar.

## Ergebnis — die Annahme der Story trifft nicht zu

Der `FileController` hat **drei** öffentliche Actions, und alle drei sind API-Funktionen. Eine
Admin-Dateiverwaltung steckt nicht darin.

| Action | Routen | Einstufung | Begründung |
|---|---|---|---|
| `uploadAction` | `POST /file/upload` | **API — bleibt** | Datei anlegen oder ersetzen, Zuordnung zu einem Ordner, Berechtigungsprüfung `PIM\File`. Der Weg, auf dem Sync und Apps Dateien einliefern. |
| `getAction` | `GET /file/get/{id}` und sechs Varianten mit `size`, `variant`, `alias` | **API — bleibt** | Auslieferung inkl. Thumbnail-Größen, ETag und bedingter Antwort. Bewusst **ohne** `checkToken` — Dateien sind über ihre ID abrufbar. |
| `overwriteAction` | `POST /file/overwrite` | **API — bleibt** | Ersetzt den Inhalt einer Datei durch den einer anderen, über `sourceId`/`destId`. |
| `sanitizeFileName` | — | intern | Hilfsmethode von `uploadAction`. |

`lib/contentfly/Classes/File/` (Backend, Processing) enthält ebenfalls keine UI-Belange:
Ein `grep` über `uiManager`, `twig`, `admin`, `frontend` liefert im gesamten Datei-Stack
**keinen Treffer**.

**Warum die Annahme falsch war:** Die Dateiverwaltung der Oberfläche war eine Angular-Ansicht im
gelöschten `lib/contentfly-ui/`. Sie verwaltete Ordner nicht über den `FileController`, sondern
über die generischen `/api`-Endpunkte auf der `Folder`-Entity. Der `FileController` kennt
`Folder` nur an einer Stelle (`FileController.php:149`) — um eine hochgeladene Datei einem
Ordner zuzuordnen, was eine API-Funktion ist.

**Folge für die Story:** In `012-003-0002` ist nichts zu entfernen. Der Wert dieser Story liegt
vollständig in `012-003-0003` — die drei Actions abzusichern, bevor der Kernel-Wechsel sie
anfasst. Der Schnitt, vor dem die Story warnte, hätte ohne diese Prüfung womöglich
API-Funktionen mitgenommen.

**Nebenbefund, nicht hier zu entscheiden:** Die Thumbnail-Größen `pim_list` und `pim_form` legt
`Helper::install()` mit `isIntern = true` an — sie existierten für die Listen- und
Formularansicht der Oberfläche. `Api.php` filtert interne Einträge aus den API-Antworten heraus
(`isIntern = false`). Ob sie mit der Oberfläche entfallen, gehört zu `012-006`, wo der
TypeManager und die Manager-Schicht durchgesehen werden.

## Verification
Die Zuordnung wird gegen die Routen des `FileControllerProvider` gegengelesen — keine Route bleibt unklassifiziert.
