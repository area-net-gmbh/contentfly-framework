---
id: 012-005-0003
title: Leser der Annotationen nachziehen
status: todo
depends_on: [012-005-0002]
---

# Leser der Annotationen nachziehen

## Context
`Type.php`, `Api.php`, `ApiController` und die Typ-Klassen lesen die Annotationen aus und bauen daraus das Schema. Was auf gelöschte Felder zugreift, bricht sonst zur Laufzeit.

## Acceptance criteria
- [ ] `Classes/Type.php`, `Classes/Api.php`, `Controller/ApiController.php` und `Classes/Types/*` greifen auf kein entferntes Feld mehr zu.
- [ ] Das Schema wird ohne Warnungen oder Notices erzeugt.
- [ ] `excludeFromSync` (`Api.php:755`), `encoded` (`StringType`, `TextareaType`) und `isFilterable` (`Type.php:117`) funktionieren unverändert.

## Verification
Schema-Erzeugung und einen Sync-Abruf durchspielen; Ergebnis mit dem Stand vor dem Umbau vergleichen — die datenrelevanten Felder sind identisch.
