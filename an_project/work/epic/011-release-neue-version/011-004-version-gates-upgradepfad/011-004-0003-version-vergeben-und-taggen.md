---
id: 011-004-0003
title: Die Version vergeben, taggen und @RC entfernen
status: todo
depends_on: [011-004-0002]
---

# Die Version vergeben, taggen und @RC entfernen

## Context
Der letzte Schritt des Epics — und der einzige, der **nach aussen** wirkt.

`version.php` und die `path`-Option im Wurzel-Manifest stehen auf `2.0.0`, und `^2.0` steht in
Runbook, `migration.md` und im Bezugsweg-Gate. Veröffentlicht sind bisher nur die Vorab-Tags
`v2.0.0-rc1` (unbrauchbar, entfernt) und `v2.0.0-rc2`.

**Das `@RC` an drei Stellen ist die Nachhut dieses Tasks.** Es steht dort, weil es noch keine
stabile Version gibt — Composer zieht Vorab-Versionen bei Standard-Stabilität nicht. Sobald
`v2.0.0` existiert, gehört es weg, und zwar an **allen drei Stellen zugleich**: Eine Constraint,
die in der Doku anders lautet als im Gate, prüft einen anderen Weg als den beschriebenen.

## Acceptance criteria
- [ ] Die Release-Nummer ist entschieden und an allen Stellen dieselbe — `version.php`, `path`-Option, Tag.
- [ ] Der Tag ist gesetzt, der Veröffentlichungslauf grün, und das Paket-Repository trägt ihn.
- [ ] `@RC` ist aus `runbook.md`, `migration.md` und `tools/ci/bezugsweg-pruefen.sh` entfernt — alle drei in einem Zug.
- [ ] Das Bezugsweg-Gate zieht danach mit **`^2.0`** eine stabile Version, nicht mehr `^2.0@RC`.
- [ ] Der Vorab-Tag `v2.0.0-rc2` ist entweder entfernt oder ausdrücklich als Vorstufe stehen gelassen — entschieden, nicht vergessen.

## Verification
Ein Projekt von aussen mit `"areanet/contentfly": "^2.0"` bekommt `2.0.0` — und nicht `dev-master`
und nicht den Vorab-Tag. Das prüft das Gate aus `011-002-0004` von selbst, sobald die Constraint
umgestellt ist.
