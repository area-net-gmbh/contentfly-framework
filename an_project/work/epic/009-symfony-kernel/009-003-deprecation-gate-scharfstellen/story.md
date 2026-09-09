---
id: 009-003-0000
title: Das Deprecation-Gate unter Symfony 7.4 scharfstellen
status: review
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
- [x] 009-003-0001 — Request::get() ablösen — 74 Stellen
- [x] 009-003-0002 — Die restlichen eigenen Deprecations beheben
- [x] 009-003-0003 — Das Gate scharfstellen und den Rest an Epic 010 übergeben

## Ergebnis

**Das Gate ist scharf, und es ist leer: PHPStan `[OK] No errors`, das Deprecation-Log 0 Zeilen
bei 0 Ausnahmen.** Von 115 PHPStan-Meldungen sind 31 übrig, alle aus Doctrine und alle benannt
an Epic `010` übergeben.

| | vorher | nachher |
|---|---|---|
| PHPStan gesamt | 115 | 31, alle ausgenommen → `[OK]` |
| davon eigener Code | 84 | **0** |
| PHPStan-Job | `allow_failure: true` | blockierend |
| Deprecation-Ausnahmen | 1 | 0 |
| Tests | 266 | 266, alle grün |

### Der grosse Posten: 74 × `Request::get()`

Symfony 7.4 hat die Methode als deprecated markiert. Sie ist **nicht mechanisch ersetzbar**: Sie
sucht der Reihe nach in `attributes`, `query` und `request`. Je Stelle bestimmt ergab: 69 ×
Nutzlast, 4 × `_controller` aus den Attributen, 1 × der `_token`-Rückfall aus Query **und**
Rumpf. Die Pfadplatzhalter kamen gar nicht vor.

**Der Umweg, den das gekostet hat:** Die erste Ersetzung durch `$request->request->get()` warf
59 Tests um — `InputBag::get()` erlaubt seit Symfony 6 **nur skalare Werte**, und `data`,
`where`, `properties`, `objects` und `order` sind Objekte oder Listen. `InputBag::all($key)` ist
kein Ausweg, es wirft umgekehrt bei Skalaren. Gelesen wird jetzt der Beutel selbst.

### Der Fund, der eine alte Zuschreibung korrigiert

PHPStan meldete `Access to undefined constant
Messages::contentfly_general_record_already_exists`. Die Konstante heisst
`…_ressource_already_exists`. Die Zeile war kein Fehlerbericht, sondern ein Fatal — und
`ConstraintApiTest` hatte den daraus folgenden 500 dem Befund aus `000-000-0006` zugeschrieben.
**Die Zuschreibung war falsch; es lag nie an der Fehlerkette.** Jetzt 409.

Dazu drei weitere Level-0-Funde, die vorher nie jemand angesehen hatte: eine Klasse, die eine
nicht existierende Schnittstelle implementiert und bei der ersten Instanziierung gestorben wäre;
eine nicht deklarierte `getId()`; und vier Cache-Instanzen, deren Konstruktor-Argument
stillschweigend verworfen wurde, sodass sich Abfrage- und Metadaten-Cache seit Jahren denselben
Namensraum teilten.

### Die Ausnahmeliste überwacht sich selbst

Beide Richtungen gemessen: Eine Ausnahme, die nichts mehr trifft, macht den Lauf rot; eine neue
eigene Deprecation ebenfalls. Das ist dieselbe Eigenschaft, die bei den beiden anderen Gates aus
`006-005` Regel 3 heisst — PHPStan bringt sie von sich aus mit, und deshalb **keine Baseline**.
