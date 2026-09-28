---
id: 015-000-0012
title: fulltext-Filter in /api/list sucht in pass und salt
status: todo
depends_on: []
---

# fulltext-Filter in /api/list sucht in pass und salt

## Context
**Security-Scan 2026-09, MEDIUM. Findings F17, F20, F27.** Alle drei beschreiben dieselbe Stelle.

`where.fulltext` von `/api/list` hängt in `Api::getList` (`Classes/Api.php:1341`) für jedes
String-Feld der Entity `<feld> LIKE '%wert%'` an. Bei `PIM\User` gehören `pass` und `salt` dazu.
`toValueObject` blendet beide in der Ausgabe aus, der Filter läuft trotzdem darüber. Die
Trefferzahl (`meta.totalItems`) wird so zum Teilstring-Orakel. `_` und `%` im Wert werden nicht
maskiert, damit lässt sich ein Treffer an eine Position binden.

Ablauf: Ein Nicht-Admin mit Leserecht auf `PIM\User` sendet
`{"entity":"PIM\\User","where":{"alias":"admin","fulltext":"<präfix><zeichen>"}}` und liest
`totalItems` (1 oder 0). Zeichen für Zeichen lassen sich so ein alter SHA-256-Hash und sein
Hex-Salt vollständig lesen, in etwa 64 × 16 Anfragen. Betroffen sind Konten, die sich seit der
Umstellung auf Argon2id in `013-001-0001` nicht angemeldet haben. Der Hash lässt sich dann offline
mit GPU-Geschwindigkeit knacken. Argon2id-Hashes lassen sich ebenfalls auslesen, unter der
Standard-Kollation allerdings ohne Gross-/Kleinschreibung.

## Acceptance criteria
- [ ] `pass`, `salt` und `externalId` sind aus `where`, `fulltext`, `order` und `groupBy` ausgeschlossen, am besten per Markierung im Schema statt per Namensliste.
- [ ] LIKE-Platzhalter (`%`, `_`, `\`) im `fulltext`-Wert werden maskiert.
- [ ] Entschieden und dokumentiert, wie mit den verbliebenen alten SHA-256-Hashes umgegangen wird (Zwangs-Reset oder Massenmigration). Eine Umsetzung davon ist ein eigener Task.

## Verification
Integrationstest: `fulltext` mit dem ersten Zeichen des bekannten Hashes eines Testkontos, einmal
richtig und einmal falsch. Vor dem Fix unterscheidet sich `totalItems` (1 gegen 0), nach dem Fix
nicht. `where: {"pass": ...}` wird abgelehnt.
