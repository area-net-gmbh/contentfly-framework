---
id: 000-000-0094
title: "/api/deleted meldet Alt-Löschungen mit 'Gelöscht' wie /api/all"
status: todo
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
- [ ] `getDeleted()` liefert Zeilen mit `mode = 'Gelöscht'` wie `DEL` — Leserecht und `lastModified` gelten gleich.
- [ ] Test: Eine `'Gelöscht'`-Zeile erscheint in `/api/deleted`; ohne Leserecht auf die Entity nicht.
- [ ] Der Sprachwächter (`EnglishOnlyTest`) kennt die neue Stelle, falls er eine eigene Ausnahme braucht.
- [ ] Registereintrag unter *API*: `/api/deleted` meldet mehr als bisher.

## Verification
Test und volle Suite. Gegenprobe: Ohne den Fix fehlt die `'Gelöscht'`-Zeile in `/api/deleted`.
