---
id: 012-001-0003
title: ExportController samt Provider und Konfiguration entfernen
status: done
depends_on: [012-001-0001]
---

# ExportController samt Provider und Konfiguration entfernen

## Context
Der Excel-Export (443 Zeilen) war eine Funktion der Oberfläche. Mit ihm verschwindet die Verwendung von `ellumilel/php-excel-writer` — dem Paket, das PHP 8 hart ausschließt.

## Acceptance criteria
- [x] `lib/contentfly/Controller/ExportController.php` ist gelöscht.
- [x] `lib/contentfly/Classes/Controller/Provider/Base/ExportControllerProvider.php` ist gelöscht.
- [x] `$app->mount('/export', …)` in `bootstrap-web.php` ist entfernt.
- [x] Der Konfigurationsschlüssel `APP_EXPORT_CONTROLLER` in `lib/contentfly/Classes/Config.php:266` ist entfernt, ebenso etwaige Overrides in `custom/config.php`.
- [x] `Ellumilel` wird nirgends mehr importiert.

## Verification
`grep -rn "ExportController\|Ellumilel\|APP_EXPORT_CONTROLLER" lib custom bin` liefert keine Treffer. Ein Aufruf von `/export/...` liefert 404 statt eines Serverfehlers.
