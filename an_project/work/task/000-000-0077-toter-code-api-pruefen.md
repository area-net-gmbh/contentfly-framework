---
id: 000-000-0077
title: Toten Code in Api.php entfernen — eigene Navigation und altes Löschkennzeichen
status: todo
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
- [ ] Je Block entschieden und begründet: entfernen (mit Registereintrag) oder behalten (mit Test).
- [ ] `FRONTEND_CUSTOM_NAVIGATION`, `PIM\Nav`, `PIM\NavItem`: gleiche Entscheidung, sonst bleibt ein halber Rest.

## Verification
Suite grün; bei Entfernung Registereintrag und `MigrationGuideTest` grün.
