---
id: 000-000-0095
title: Eine i18n-Sperre antwortet mit 403 statt 550
status: todo
depends_on: []
---

# Eine i18n-Sperre antwortet mit 403 statt 550

## Context
**Aus `000-000-0074`.** Darf eine Gruppe eine Sprache nicht schreiben, lehnen `doInsert`, `doUpdate`
und `doDelete` mit `ContentflyI18NException` ab. Die Klasse setzt seit 1.x den Code **550**, und der
Envelope macht ihn zum HTTP-Status.

**550 ist kein HTTP-Status.** Als 5xx liest er sich wie ein Serverfehler: Ein Client oder Proxy darf
wiederholen, ein Monitoring zählt ihn als Ausfall. Jede andere Rechte-Ablehnung der API antwortet
403. Der Fehlercode `contentfly_i18n_permission_denied` stimmt — nur der Status nicht.

`PermissionBranchApiTest` hält 550 heute als Ist-Zustand fest.

## Acceptance criteria
- [ ] Die drei i18n-Sperren antworten mit **403**; der Fehlercode bleibt `contentfly_i18n_permission_denied`.
- [ ] Geprüft, wo `ContentflyI18NException` sonst geworfen wird, und für jede Stelle der richtige Status festgelegt.
- [ ] `PermissionBranchApiTest` erwartet 403, mit Verweis auf diesen Task.
- [ ] Registereintrag unter *API*: Ein Client, der auf 550 geprüft hat, prüft auf 403 und den Fehlercode.

## Verification
Tests und volle Suite.
