---
id: 012-001-0001
title: UiController und seine Routen entfernen
status: done
depends_on: []
---

# UiController und seine Routen entfernen

## Context
Der `UiController` rendert `app.twig` und ist der Einstieg in die Admin-Oberfläche. Er hat keine API-Funktion — mit der Oberfläche fällt er ersatzlos.

## Acceptance criteria
- [x] `lib/contentfly/Controller/UiController.php` ist gelöscht.
- [x] Die Service-Definition `$app['ui.controller']` in `lib/contentfly/bootstrap-web.php` ist entfernt.
- [x] Die vier Routen-Registrierungen auf `ui.controller:showAction` (rund um `FRONTEND_URL`) sind entfernt — sie sind heute teils doppelt eingetragen.
- [x] Kein Verweis auf `UiController` oder `ui.controller` mehr im Baum.

## Verification
`grep -rn "UiController\|ui\.controller" lib custom bin index.php` liefert keine Treffer. Die Anwendung bootet, `/api`-Aufrufe antworten wie vorher.
