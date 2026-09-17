---
id: 011-004-0003
title: Die Version vergeben, taggen und @RC entfernen
status: done
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
- [x] Die Release-Nummer ist entschieden und an allen Stellen dieselbe — `version.php`, `path`-Option, Tag.
- [x] Der Tag ist gesetzt, der Veröffentlichungslauf grün, und das Paket-Repository trägt ihn.
- [x] `@RC` ist aus `runbook.md`, `migration.md` und `tools/ci/bezugsweg-pruefen.sh` entfernt — alle drei in einem Zug.
- [x] Das Bezugsweg-Gate zieht danach mit **`^2.0`** eine stabile Version, nicht mehr `^2.0@RC`.
- [x] Der Vorab-Tag `v2.0.0-rc2` ist entweder entfernt oder ausdrücklich als Vorstufe stehen gelassen — entschieden, nicht vergessen.

## Verification
Ein Projekt von aussen mit `"areanet/contentfly": "^2.0"` bekommt `2.0.0` — und nicht `dev-master`
und nicht den Vorab-Tag. Das prüft das Gate aus `011-002-0004` von selbst, sobald die Constraint
umgestellt ist.

## Ergebnis
**`v2.0.0` steht.** Quell-Repository `635325db`, Paket-Repository `b8e22a7a`.

**Die Reihenfolge war nicht beliebig.** Das `@RC` konnte nicht im selben Pull Request fallen, in
dem der Tag entsteht: Das Bezugsweg-Gate läuft **im** Pull Request, und mit `^2.0` hätte es dort
eine stabile Version gesucht, die es noch nicht gab. Also erst Merge, dann Tag, dann diese
Änderung — die das Gate nun seinerseits beweist.

**Ein Nebenbefund, der den Subtree-Split bestätigt:** `v2.0.0` und der entfernte `v2.0.0-rc2`
zeigten im Paket-Repository auf **denselben** Commit. `lib/contentfly/` hat sich seit rc2 nicht
geändert — `011-003` und `011-004` betrafen Vorlage, Doku und Tests, also das Skeleton. Gleicher
Eingang, gleicher Ausgang: genau die Eigenschaft, wegen der `git subtree split` gewählt wurde.

**Abnahme gemessen, nicht angenommen.** Frisches Verzeichnis, `"areanet/contentfly": "^2.0"`,
`composer update`:

```
version : v2.0.0
source  : git git@github.com:area-net-gmbh/contentfly-framework-dist.git
ref     : b8e22a7a9d69795374ed91d5742a42c7b2775d1e
51 Pakete
```

Kein `dev-master`, kein Vorab-Tag, kein Pfad.

**Der Vorab-Tag ist entfernt**, aus beiden Repositories. Entschieden, nicht vergessen: rc2 war ein
Testlauf des Bezugswegs, kein Angebot an ein Projekt. Das Paket-Repository trägt jetzt genau einen
Tag.

**Was das Gate ab jetzt zusätzlich prüft:** Ohne `@RC` fällt ein fehlendes `v2.0.0` nicht mehr
still auf einen Vorab-Tag zurück, sondern macht den Lauf rot.
