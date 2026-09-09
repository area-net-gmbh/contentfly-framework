---
id: 009-003-0000
title: Das Deprecation-Gate unter Symfony 7.4 scharfstellen
status: in-progress
depends_on: [009-002-0000]
---

# Das Deprecation-Gate unter Symfony 7.4 scharfstellen

## Goal
Das Gate „0 Deprecations" aus `006-005` läuft heute mit **einer** Ausnahme: der Deprecation aus
Silex. Mit Silex fällt ihr Grund weg. Nach dieser Story ist
`tools/ci/deprecations-ausnahmen.txt` leer, und die Prüfung, die eine überflüssig gewordene
Ausnahme meldet, hat es bestätigt.

Der Punkt ist nicht die leere Datei, sondern was ein Rest darin bedeuten würde: Der Schnitt
hätte neue Schulden aufgenommen, statt die alten zu tilgen. Und die Zusicherung, dass der
spätere Sprung auf Symfony 8.4 LTS ein Constraint-Bump bleibt, hängt genau daran.

Dazu gehört die zweite Hälfte des Gates: PHPStan läuft heute auf **Level 0** und ist nicht
blockierend. Unter dem neuen Kernel ist zu prüfen, was ein höheres Level kostet und ob es
blockierend werden kann — entschieden wird das hier, nicht angenommen.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 009-003-0001 — Request::get() ablösen — 74 Stellen
- [ ] 009-003-0002 — Die restlichen eigenen Deprecations beheben
- [ ] 009-003-0003 — Das Gate scharfstellen und den Rest an Epic 010 übergeben
