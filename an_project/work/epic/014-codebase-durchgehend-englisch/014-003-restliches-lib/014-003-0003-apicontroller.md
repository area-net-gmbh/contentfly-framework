---
id: 014-003-0003
title: ApiController auf Englisch
status: review
depends_on: [014-003-0002]
---

# ApiController auf Englisch

## Context
`Controller/ApiController.php` hat 1.236 Zeilen und mit rund 170 markierten Zeilen die höchste
Dichte deutscher Texte im Paket. Er bekommt deshalb einen eigenen Task.

Umfang: alle Variablen, Meldungen, apidoc-Blöcke und Kommentare. Die Actions und Routen sind
englisch und bleiben.

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

**`ApiController.php` ist englisch, und am Code hat sich nichts geändert.** Die rund 170
markierten Zeilen standen ausschliesslich in Kommentaren und apidoc-Blöcken. Ein Scan aller
Code-Token (Variablen, Bezeichner, Strings) mit dem Detektor des Sprachwächters fand keinen
deutschen Namen und keinen deutschen Text. Die Code-Token sind nachweislich identisch mit dem Stand
davor.

**Übersetzt:**
- die langen Erklärblöcke in `configAction`, `listAction`, `multiupdateAction`, `replaceAction`
  und `singleAction`
- alle apidoc-Beschreibungen und Fehlertexte
- die apidoc-Gruppen und -Namen, etwa `@apiGroup Objekte` → `Objects` und `Baumansicht` →
  `Tree view`
- die Beispielwerte: `feld1` → `field1`, `"Kunden"` → `"Customers"`, `kundennummer` →
  `customerNumber`

**Bewusst wörtlich übernommen, nicht korrigiert:** Die apidoc-Beschreibung von `update` sprach
schon vorher vom „Anlegen eines neuen Objekts", die von `replace` vertauscht insert und update.
Das ist ein vorbestehender Fehler in der Doku. Ihn zu korrigieren ist keine Übersetzung und
gehört nicht in diesen Task.

Geprüft: volle Suite `OK (528 tests, 1702 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün.
