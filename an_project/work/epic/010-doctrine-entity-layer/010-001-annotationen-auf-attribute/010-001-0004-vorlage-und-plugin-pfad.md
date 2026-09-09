---
id: 010-001-0004
title: Die Vorlage umstellen und den Plugin-Pfad entscheiden
status: todo
depends_on: [010-001-0003]
---

# Die Vorlage umstellen und den Plugin-Pfad entscheiden

## Context
`custom/Entity/Core/Example.php` trägt 10 `@ORM\*` und 2 `@PIM\*`. Sie liegt im Namensraum
`Custom\Entity` und hat deshalb ihren eigenen Treiber in der Chain — sie lässt sich getrennt
umstellen.

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
- [ ] `custom/Entity/Core/Example.php` trägt Attribute; der Treiber für `Custom\Entity` ist ein `AttributeDriver`.
- [ ] Die Kommentare der Vorlage erklären den Attribut-Weg, nicht den alten — einschliesslich der Stelle, an der `@PIM\Select` seine Optionen deklariert.
- [ ] `Classes/Plugin.php` benutzt denselben Treiber wie der Rest; dass der Pfad mangels Plugin nicht ausführbar ist, steht als Einschränkung im Ergebnis und als Kommentar an der Stelle.
- [ ] `VorlageApiTest` bleibt grün und prüft die Vorlage weiterhin über HTTP.

## Verification
`./vendor/bin/phpunit --filter VorlageApiTest`, dann die volle Suite. Zusätzlich ein Schreib- und
Lesevorgang gegen `example_entity` über die API, damit die Umstellung nicht nur beim Lesen des
Schemas belegt ist.
