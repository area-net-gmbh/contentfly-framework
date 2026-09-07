---
id: 012-005-0000
title: @PIM-Annotationen entrümpeln
status: in-progress
depends_on: [012-001-0000]
---

# @PIM-Annotationen entrümpeln

## Goal
Das `@PIM`-System ist überwiegend ein Beschreibungssystem für Formulare, nicht für Daten. Ohne
Oberfläche verliert der größte Teil davon seinen Zweck. Diese Story trennt, was bleibt, von dem,
was geht.

**Das ist der härteste Bruch für Bestandsprojekte** — diese Annotationen stehen in *deren*
Entities, nicht nur hier.

**Umfang**
- **Widget-Annotationen löschen** — von den 15 Klassen in `lib/contentfly/Classes/Annotations/`
  beschreiben zwölf reine Formularelemente: `Rte` (trägt eine TinyMCE-Toolbar-Zeile), `Checkbox`,
  `Radio`, `Select`, `Textarea`, `MatrixChooser`, `Datetime`, `Time`, `Password`,
  `EntitySelector`, `Virtualjoin` — jede einzeln prüfen, ob wirklich nur UI daran hängt.
  `Permissions` und `I18nPermissions` bleiben: sie tragen Berechtigungen, also Datenverhalten.
- **`Config` aufteilen.** Von 24 Feldern bleiben die datenrelevanten:

  | Bleibt | Warum |
  |---|---|
  | `excludeFromSync` | steuert die Sync-API — `Classes/Api.php:755` |
  | `encoded` | steuert die Verschlüsselung — `StringType`, `TextareaType` |
  | `isFilterable` | API-Filter — `Classes/Type.php:117` |
  | `unique`, `type`, `i18n_universal` | Datenmodell |
  | `sortBy`, `sortOrder`, `sortRestrictTo` | Sortierung der API-Antworten |

  Es fallen: `viewMode`, `showInList`, `listShorten`, `hide`, `label`, `labelProperty`, `tab`,
  `tabs`, `sort`, `isDatalist`, `isSidebar`, `lines`, `accept`.
  **`readonly` und `filter` einzeln prüfen** — `readonly` kann ein UI-Hinweis oder ein echter
  Schreibschutz der API sein. Das ist der Punkt, an dem versehentlich eine Schutzwirkung
  verschwinden könnte.
- **Leser nachziehen:** `Classes/Type.php`, `Classes/Api.php`, `Controller/ApiController.php`,
  `Classes/Types/*` lesen die Annotationen aus und bauen daraus das Schema. Was dort auf
  gelöschte Felder zugreift, muss mit.
- **Die eigenen Entities des Frameworks bereinigen** (`lib/contentfly/Entity/`, `custom/Entity/`).

**Entschieden am 2026-09-04: hart entfernen.** Die gestrichenen Felder verschwinden ersatzlos;
es gibt keine Duldungsphase. Ein Bestandsprojekt, dessen Entities `@PIM\Config(label=…, tab=…)`
enthalten, muss sie entfernen — dafür liefert Epic 007 eine Rector-Regel. Der Vorteil: keine
zweite Wahrheit im Code, kein Deprecation-Pfad, der drei Jahre lang mitgeschleppt wird. Der Preis:
Der Umstieg ist ein Schnitt, kein Übergang — genau das muss der Migrationsleitfaden klar sagen.

**Fertig, wenn**
- Nur noch datenrelevante `@PIM`-Annotationen existieren.
- Das Schema, das die API ausliefert, enthält keine UI-Felder mehr — und was Sync-Clients
  tatsächlich brauchen, ist unverändert vorhanden.
- Die gestrichenen Felder sind ersatzlos weg — kein Rest, der wirkungslos toleriert wird.
- Die Liste der entfernten Felder liegt für Epic 007 bereit (Grundlage der Rector-Regel).

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 012-005-0001 — Widget-Annotationen löschen
- [ ] 012-005-0002 — Config-Annotation auf datenrelevante Felder reduzieren
- [ ] 012-005-0003 — Leser der Annotationen nachziehen
- [ ] 012-005-0004 — Entities des Frameworks und der Vorlage bereinigen
