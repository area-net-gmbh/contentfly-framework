---
id: 000-000-0095
title: Eine i18n-Sperre antwortet mit 403 statt 550
status: review
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
- [x] Die drei i18n-Sperren antworten mit **403**; der Fehlercode bleibt `contentfly_i18n_permission_denied`.
- [x] Geprüft, wo `ContentflyI18NException` sonst geworfen wird, und für jede Stelle der richtige Status festgelegt.
- [x] `PermissionBranchApiTest` erwartet 403, mit Verweis auf diesen Task.
- [x] Registereintrag unter *API*: Ein Client, der auf 550 geprüft hat, prüft auf 403 und den Fehlercode.

## Verification
Tests und volle Suite.

## Ergebnis (2026-09-25)
**Keine i18n-Ablehnung antwortet mehr mit 550.** `ContentflyI18NException` nimmt den Status jetzt als
Parameter, wie `ContentflyException`; die Vorgabe ist 403.

| Stelle | Fehlercode | Status |
|---|---|---|
| `doInsert`, `doUpdate`, `doDelete` | `contentfly_i18n_permission_denied` | **403** — eine Rechte-Ablehnung wie jede andere der API |
| `getSingle` mit `compareToLang`, `join` und `multijoin` | `contentfly_i18n_missing_translations` | **409** — die Anfrage ist gültig, die beiden Sprachen widersprechen sich; weder Rechtefrage (403) noch fehlerhafte Anfrage (400) |
| `doDelete`, `translations_exists` | — | auskommentiert seit 1.x, unverändert |

**Tests:** `PermissionBranchApiTest` erwartet für die drei Sperren 403; `ReadPathApiTest` prüft den
`compareToLang`-Konflikt jetzt genau — 409 und `contentfly_i18n_missing_translations` statt nur
„nicht 200". **Gegenprobe:** Gegen den alten Code sind alle vier rot, jeweils mit 550.

**Registereintrag** unter *API* mit beiden Codes; Leitfaden 143 Einträge, 49 unter *API*.

**Geprüft:** volle Suite auf frischer Installation 862 grün (3 übersprungen wie auf `master`), PHPStan
ohne Fehler, keine Deprecation im Server-Log.
