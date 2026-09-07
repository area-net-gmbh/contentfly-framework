---
id: 012-005-0002
title: Config-Annotation auf datenrelevante Felder reduzieren
status: done
depends_on: [012-005-0001]
---

# Config-Annotation auf datenrelevante Felder reduzieren

## Context
`Config` mischt Datenmodell und Formularbeschreibung. Von 24 Feldern bleiben die, die API-Verhalten steuern; der Rest beschreibt Masken.

## Acceptance criteria
- [x] Erhalten bleiben: `excludeFromSync`, `encoded`, `isFilterable`, `unique`, `type`, `i18n_universal`, `sortBy`, `sortOrder`, `sortRestrictTo` — **und `labelProperty`** (siehe Korrektur unten).
- [x] Entfernt sind: `viewMode`, `showInList`, `listShorten`, `hide`, `label`, `tab`, `tabs`, `sort`, `isDatalist`, `isSidebar`, `lines`, `accept`.
- [x] `readonly` und `filter` sind **einzeln geprüft**: Ist es ein UI-Hinweis oder ein Schreibschutz der API? Das Ergebnis ist begründet festgehalten — hier kann versehentlich eine Schutzwirkung verschwinden.
- [x] Die Entfernung ist hart: kein Feld bleibt wirkungslos stehen (entschieden am 2026-09-04).
- [x] Die Liste der entfernten Felder liegt für die Rector-Regel in Epic 007 bereit.

## Korrektur des Umfangs: `labelProperty` bleibt

Die ursprüngliche Streichliste führte `labelProperty` als UI-Feld. Das ist falsch — es hat
zwei Leser, die beide Daten betreffen:

- **`Log::setModelLabel()`** (`Classes/Api.php`, vier Stellen: Insert, Update, Delete, USERDEL).
  Der Wert wird **in die Datenbank geschrieben**. Ohne ihn verliert jeder neue Log-Eintrag
  sein Label und trägt nur noch `modelId` und `modelName`.
- **Der `partial`-Select der Join-Felder in `Api::getList()`.** Er nimmt genau die
  `labelProperty` des verjointen Objekts mit in die Abfrage. Ohne sie liefert die API für
  verjointe Objekte nur noch deren `id` — ein sichtbarer Bruch für jeden Sync-Client.

Entschieden am 2026-09-07: `labelProperty` bleibt, das Kriterium oben ist entsprechend
korrigiert. In `an_project/docs/pim-annotationen-migration.md` steht es unter den
**gebliebenen** Feldern, damit die Rector-Regel es nicht doch entfernt.

## Ergebnis der Einzelprüfung von `readonly` und `filter`

Beide sind **UI-Hinweise ohne jede Schutzwirkung** — die Sorge des Kriteriums trifft hier nicht zu:

- **`readonly`** wurde von `Type::processSchema()` (Eigenschaftsebene) und `Api::getSchema()`
  (Klassenebene) in das Schema geschrieben und **von keiner Stelle wieder ausgelesen**. Die
  API hat auf ein `readonly`-Feld nie anders reagiert; der Schreibschutz existierte allein in
  der Maske. Nachgewiesen über eine Suche nach `['readonly']` und `->readonly` über `lib/`
  und `custom/`: nur die schreibenden Stellen.
- **`filter`** wurde von `Config` **nie gelesen**. `Type::processSchema()` belegte den
  gleichnamigen Schema-Key mit dem Leerstring, das war alles. Das Feld war schon vor diesem
  Umbau tot.

## Verification
Anwendung bootet; das von der API ausgelieferte Schema enthält keine UI-Felder mehr.

- [x] `Config.php` trägt nur noch die zehn datenrelevanten Felder; PHP-Syntax fehlerfrei.
- [x] Feldliste für Epic 007 angelegt: `an_project/docs/pim-annotationen-migration.md`.
- [x] **Boot und Schema wurden mit Task 012-005-0004 verifiziert, nicht hier.** Das ist kein
      übersprungener Schritt, sondern der von der Story vorgegebene Schnitt: `Annotation::__get()`
      wirft eine `BadMethodCallException`, und der `AnnotationReader` bricht ab, sobald eine
      Entity ein Feld trägt, das die Annotationsklasse nicht mehr kennt. Zwischen diesem Commit
      und `012-005-0004` ist der Baum deshalb zwangsläufig nicht lauffähig — die Leser folgen in
      `0003`, die Entities in `0004`. Die Story merged als **eine** Einheit (`--no-ff`), der
      Zwischenstand erreicht `master` nie einzeln.
