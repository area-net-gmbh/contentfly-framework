---
id: 000-000-0094
title: "/api/deleted meldet Alt-Löschungen mit 'Gelöscht' wie /api/all"
status: done
depends_on: []
---

# /api/deleted meldet Alt-Löschungen mit 'Gelöscht' wie /api/all

## Context
**Aus `000-000-0077`.** Contentfly 1.x schrieb für eine Löschung `'Gelöscht'` in `pim_log.mode`.
`getAll()` meldet solche Zeilen weiter als Löschung — entschieden am 2026-09-14 (`014-003-0002`) mit
der Begründung: *Bestandsprojekte haben solche Zeilen, und Sync-Clients müssen diese Löschungen weiter
bekommen.*

**Die andere Hälfte des Sync-Vertrags hält sich nicht daran.** `getDeleted()` fragt nur
`mode = 'DEL'` und `USERDEL` ab. Ein Client, der Löschungen über `/api/deleted` holt, erfährt von
einer Alt-Löschung nie; über `/api/all` schon. Das Objekt bleibt auf dem Client stehen.

## Acceptance criteria
- [x] `getDeleted()` liefert Zeilen mit `mode = 'Gelöscht'` wie `DEL` — Leserecht und `lastModified` gelten gleich.
- [x] Test: Eine `'Gelöscht'`-Zeile erscheint in `/api/deleted`; ohne Leserecht auf die Entity nicht.
- [x] Der Sprachwächter (`EnglishOnlyTest`) kennt die neue Stelle, falls er eine eigene Ausnahme braucht.
- [x] Registereintrag unter *API*: `/api/deleted` meldet mehr als bisher.

## Verification
Test und volle Suite. Gegenprobe: Ohne den Fix fehlt die `'Gelöscht'`-Zeile in `/api/deleted`.

## Ergebnis (2026-09-25)
**`/api/deleted` meldet Zeilen mit `mode = 'Gelöscht'` wie `DEL`** — beide Hälften des Sync-Vertrags
behandeln die Alt-Löschungen jetzt gleich.

- `getDeleted()`: `mode = 'DEL' OR mode = 'Gelöscht' OR (mode = 'USERDEL' AND users = ?)`, mit Kommentar,
  der auf `014-003-0002` verweist. Leserecht (`0061`) und `lastModified` greifen unverändert.
- **Tests** in `ReadPathApiTest`, neben dem `'Gelöscht'`-Test für `/api/all` aus `0075`: Die Alt-Löschung
  erscheint in `/api/deleted`, und nur mit Leserecht auf die Entity. **Gegenprobe:** Gegen den alten
  Code sind beide rot.
- **Sprachwächter:** Die bestehenden Ausnahmen für `Api.php` und `ReadPathApiTest.php` decken die neuen
  Stellen; die Begründung für `ReadPathApiTest.php` nennt jetzt auch `/api/deleted`.
- **Registereintrag** unter *API*; Leitfaden 142 Einträge, 48 unter *API*.

**Geprüft:** volle Suite auf frischer Installation 862 grün (3 übersprungen wie auf `master`), PHPStan
ohne Fehler, keine Deprecation im Server-Log.
