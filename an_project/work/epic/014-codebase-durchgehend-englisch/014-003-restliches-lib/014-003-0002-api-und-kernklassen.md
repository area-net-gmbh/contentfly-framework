---
id: 014-003-0002
title: Api.php und Kernklassen auf Englisch
status: review
depends_on: [014-003-0001]
---

# Api.php und Kernklassen auf Englisch

## Context
`Classes/Api.php` (2.402 Zeilen) ist die Datenmaschine hinter `/api/*` und trägt die meisten
Berechtigungsprüfungen.

Umfang: `Classes/Api.php`, `Permission.php`, `I18nPermission.php`, `Auth.php`, `Helper.php`,
`Messages.php`, `Event.php` und `Plugin.php`, jeweils mit Namen, Meldungen und Kommentaren. Die
Message-Keys (`contentfly_general_…`) sind englisch und API-Vertrag; sie bleiben.

## Acceptance criteria
- [x] Die Dateien des Tasks sind vollständig englisch: Namen, Methoden, Eigenschaften, Variablen, Konstanten, Meldungen und Kommentare.
- [x] Werte, die gespeichert oder über die API ausgeliefert werden (Tabellen- und Spaltennamen, Message-Keys, Token-`purpose`), bleiben unverändert, wenn sie schon englisch sind. Ein deutscher gespeicherter Wert wird nur nach Rückfrage geändert.
- [x] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`, `dev-guide.md`, soweit ein Test daran abgleicht). Testklassen, die nach einer umbenannten Klasse heissen, sind mit umbenannt.
- [x] Die Ausnahmen im Sprachwächter, die dieser Task auflöst, sind gestrichen.
- [x] Verhalten unverändert: volle Suite mit gleicher Testzahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan (Result-Cache geleert),
`tools/ci/deprecations-pruefen.sh`, Nachsuche nach jedem alten Namen (auch in umgebrochenen Aufrufen),
Suche nach deutschen Wörtern in den Dateien des Tasks, `sh tools/check-template-config.sh`.

## Ergebnis

**`Classes/Api.php` und die sieben Kernklassen sind englisch.** Das umfasst `Permission`,
`I18nPermission`, `Auth`, `Helper`, `Messages`, `Event` und `Plugin`. Die Kommentare haben zwei
Agenten übersetzt, rund 30 Blöcke allein in `Api.php`. In allen acht Dateien sind die Code-Token
nachweislich identisch mit dem Stand davor. Der Agent für `Api.php` hat zwei deutsche Kommentare
gefunden, die die Heuristik nicht erkannt hatte (`//Baumstruktur aktualisieren`,
`//Protokollierung`).

**Im Code:**
- Die Warnung beim Schemaaufbau lautet jetzt „No Contentfly type matches %s::%s (column type
  "%s") — the field is missing from the API schema."
- `$metadaten` → `$metadata`

Die sieben Kernklassen hatten keine deutschen Namen. Die Message-Keys (`contentfly_general_…`)
sind englisch und API-Vertrag; sie bleiben.

**Ein deutscher Wert bleibt, auf Entscheidung des Auftraggebers vom 2026-09-14:** Der Sync
fragt Löschungen mit `log.mode = 'DEL' OR log.mode = 'Gelöscht'` ab. `'Gelöscht'` ist kein Name,
sondern ein Wert, den Contentfly 1.x in `pim_log.mode` geschrieben hat. Bestandsprojekte haben
solche Zeilen, und Sync-Clients müssen diese Löschungen weiter bekommen. Ein englischer Kommentar
an der Stelle erklärt das. Im Sprachwächter wird der Wert beim Erweitern auf ganz `lib/contentfly`
(`014-003-0007`) eine benannte Ausnahme.

`Auth.php` verweist im Kommentar noch auf `BaseControllerProvider::anmelden()`. Das benennt
`014-003-0004` um.

Geprüft: volle Suite `OK (528 tests, 1702 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün. Der Detektor des Sprachwächters findet in den acht Dateien nur den
Altdaten-Wert und den Verweis auf `anmelden()`.
