---
id: 000-000-0077
title: Toten Code in Api.php entfernen — eigene Navigation und altes Löschkennzeichen
status: done
depends_on: []
---

# Toten Code in Api.php entfernen — eigene Navigation und altes Löschkennzeichen

## Context
**Aus `000-000-0069`.** Zwei offene Blöcke gehören zu Funktionen, deren Nutzer nicht mehr existieren:

- **`getExtendedSchema()`, 34 Zeilen:** baut `frontend.customNavigation.items` aus `PIM\Nav`, wenn
  `FRONTEND_CUSTOM_NAVIGATION` an ist. Die Navigation war die der PIM-Oberfläche (Epic `012`).
  `000-000-0010` hat den Schlüssel `customNavigation` behalten — zu prüfen, ob ein Client ihn liest.
- **`getAll()`:** sucht Löschungen auch unter `log.mode = 'Gelöscht'` — das Vokabular vor `000-000-0015`.

## Acceptance criteria
- [x] Je Block entschieden und begründet: entfernen (mit Registereintrag) oder behalten (mit Test).
- [x] `FRONTEND_CUSTOM_NAVIGATION`, `PIM\Nav`, `PIM\NavItem`: gleiche Entscheidung, sonst bleibt ein halber Rest.

## Verification
Suite grün; bei Entfernung Registereintrag und `MigrationGuideTest` grün.

## Ergebnis (2026-09-25)
**Die Navigation ist entfernt, samt Entities und Schalter. `'Gelöscht'` bleibt, und es ist bereits
getestet.** Entschieden am 2026-09-25.

### Block 1 — `customNavigation`: entfernt
`customNavigation` baute die Menüs der PIM-Oberfläche: Routen `#/list/<entity>`, als Symbol ein
Glyphicon. Framework und Vorlage lesen ihn nirgends. `0010` hatte ihn als „Daten“ stehen lassen,
`0043` den Tabellennamen `pim_navItem` festgeschrieben — beide mit dem Argument, die Entities
gehörten zum Datenmodell. Ohne die Oberfläche haben sie keinen Leser mehr.

| Was | geändert |
|---|---|
| `Api::getExtendedSchema()` | `frontend` trägt nur noch `languages`; die Abfrage auf `PIM\NavItem` samt `OWN`/`GROUP`-Verengung ist weg (34 von 48 Zeilen waren offen) |
| `Api::getSchema()` | `PIM\Nav` und `PIM\NavItem` nicht mehr in der Entity-Liste |
| `lib/contentfly/Entity/Nav.php`, `NavItem.php` | gelöscht |
| `Config::$FRONTEND_CUSTOM_NAVIGATION` | gestrichen |
| DOC von `/api/schema`, `STRUCTURE.md`, `pim-annotationen-migration.md`, Kommentare | nachgezogen |
| Tests | `RouteSecurityApiTest::testTheSchemaNoLongerCarriesTheNavigationOfTheDeletedUi` neu. Vier Tests nahmen `PIM\Nav` als Beispiel-Entity und nehmen jetzt `PIM\Folder`; `SyncApiTest` zählt fünf statt sieben Entities mit `excludeFromSync` |

**Registereintrag** unter *API*, dazu Verweise in den drei überholten Einträgen (`0010` ×2, `0043`).
Leitfaden: 139 Einträge, 45 unter *API*, Phase 4 erklärt die neuen `DROP`-Zeilen, Phase 5 nennt den
Schalter.

**Gemessen, was ein Bestandsprojekt erlebt** — Installation mit altem Stand, eine Zeile in `pim_nav`:

| | `orm:schema-tool:update --dump-sql` |
|---|---|
| nach dem Update, ohne weiteres | fünf `DROP FOREIGN KEY`, `DROP TABLE pim_nav`, `DROP TABLE pim_navItem` |
| beide Entities nach `custom/Entity/` übernommen, Tabellennamen behalten | `Nothing to update` — die Zeile bleibt |

Ein Projekt, das `FRONTEND_CUSTOM_NAVIGATION` noch setzt, bekommt keinen Fehler: `Config` erlaubt
eigene Schlüssel.

### Block 2 — `'Gelöscht'` in `getAll()`: bleibt
Entschieden hat das schon der Auftraggeber am 2026-09-14 (`014-003-0002`): Contentfly 1.x hat den Wert
in `pim_log.mode` geschrieben, Bestandsprojekte haben solche Zeilen, und Sync-Clients müssen diese
Löschungen bekommen. Der Test existiert seit `0075`:
`ReadPathApiTest::testAllReportsDeletionsFromTheLog` legt je eine Zeile mit `DEL` und mit `'Gelöscht'`
an und erwartet beide als Löschung.

### Geprüft
Voller Lauf auf frischer Installation mit PCOV: **866 Tests grün**, 3 übersprungen wie auf `master`;
`SchemaValidationTest` grün, PHPStan ohne Fehler, keine Deprecation im Server-Log. `Api.php`:
1.043 / 1.197 Zeilen (**87,1 %**), `getExtendedSchema` 9 / 9.

### Befund, nicht Teil dieses Tasks
**`/api/deleted` liefert die `'Gelöscht'`-Zeilen nicht.** `getDeleted()` fragt nur `DEL` und
`USERDEL` ab. Die Begründung von `014-003-0002` — Sync-Clients müssen diese Löschungen bekommen — gilt
also nur für die eine Hälfte des Sync-Vertrags: `/api/all` meldet eine Alt-Löschung, `/api/deleted`
verschweigt sie.
