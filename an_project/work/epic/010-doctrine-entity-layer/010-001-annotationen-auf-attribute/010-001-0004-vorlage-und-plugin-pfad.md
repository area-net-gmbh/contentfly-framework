---
id: 010-001-0004
title: Die Vorlage umstellen und den Plugin-Pfad entscheiden
status: todo
depends_on: [010-001-0003]
---

# Die Vorlage umstellen und den Plugin-Pfad entscheiden

## Context
> **Umfangskorrektur, 2026-09-09.** Hier stand, die Entity liesse sich getrennt umstellen, weil
> `Custom\Entity` einen eigenen Treiber in der Chain hat. **Das trägt nicht** — Begründung und
> Messung stehen in `010-001-0003`, das sie deshalb mit umgestellt hat, zusammen mit
> `custom/Traits/User.php`. Was hier bleibt, ist die **Erklärung** der Vorlage und der
> Plugin-Pfad.

`custom/Entity/Core/Example.php` trug 10 `@ORM\*` und 2 `@PIM\*` und steht seit `010-001-0003`
auf Attributen. Ihre Kommentare beschreiben aber weiterhin den Annotations-Weg.

**Sie ist der wichtigere Teil dieses Tasks, obwohl sie die kleinere Datei ist.** Die Vorlage ist
das, was ein Bestandsprojekt als Referenz bekommt; bleibt sie auf Annotationen, lehrt sie den
Weg, den das Framework gerade verlassen hat. Genau dieser Fehler ist in `009-004-0001` teuer
geworden.

**Der Plugin-Pfad ist zu entscheiden, nicht zu erraten.** `Classes/Plugin.php:107` baut für jedes
Plugin einen eigenen `newDefaultAnnotationDriver()`. `plugins/` ist leer — das war schon in
`006-003-0002` so, wo sich `AnnotationRegistry::registerFile()` an nichts zeigen liess. Ein Pfad,
den niemand ausführt, lässt sich nicht durch Ausprobieren umstellen; er wird mitgezogen und die
fehlende Prüfbarkeit ausdrücklich festgehalten.

## Acceptance criteria
- [x] `custom/Entity/Core/Example.php` trägt Attribute (erledigt mit `010-001-0003`, siehe Umfangskorrektur oben).
- [ ] Die Kommentare der Vorlage erklären den Attribut-Weg, nicht den alten — einschliesslich der Stelle, an der `@PIM\Select` seine Optionen deklariert.
- [ ] `Classes/Plugin.php` benutzt denselben Treiber wie der Rest; dass der Pfad mangels Plugin nicht ausführbar ist, steht als Einschränkung im Ergebnis und als Kommentar an der Stelle.
- [ ] `VorlageApiTest` bleibt grün und prüft die Vorlage weiterhin über HTTP.

## Verification
`./vendor/bin/phpunit --filter VorlageApiTest`, dann die volle Suite. Zusätzlich ein Schreib- und
Lesevorgang gegen `example_entity` über die API, damit die Umstellung nicht nur beim Lesen des
Schemas belegt ist.
