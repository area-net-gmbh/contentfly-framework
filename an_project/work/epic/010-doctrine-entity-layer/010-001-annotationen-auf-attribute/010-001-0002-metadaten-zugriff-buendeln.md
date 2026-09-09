---
id: 010-001-0002
title: Den Zugriff auf die eigenen Metadaten an einer Stelle bündeln
status: todo
depends_on: [010-001-0001]
---

# Den Zugriff auf die eigenen Metadaten an einer Stelle bündeln

## Context
Drei Stellen bauen sich heute jede für sich einen `AnnotationReader`:
`Classes/Api.php:1524`, `Classes/Types/JoinBidirectionalType.php:57` und — als reiner Import,
ohne Verwendung — `Controller/ApiController.php:25`.

Solange sie einzeln stehen, muss jede von ihnen mit `010-001-0003` gleichzeitig umgestellt
werden, sonst liest sie Docblocks, die es nicht mehr gibt. Gebündelt hinter **einer** Klasse ist
der Wechsel ein Einzeiler an einer Stelle — und die Umstellung der Entities lässt sich in eigene
Tasks schneiden, statt in einem Zug erledigt werden zu müssen.

Die Bündelung liest zunächst **weiterhin Annotationen**. Sie ändert kein Verhalten; sie schafft
die Fuge. Dieselbe Konstruktion wie `Kernel\Application` gegenüber Silex in `009-001`, und aus
demselben Grund.

**Der ungenutzte Import in `ApiController` ist ein eigener kleiner Befund** — er ist zu
entfernen, nicht mitzuschleppen.

## Acceptance criteria
- [ ] Eine Klasse liefert die Klassen- und Property-Metadaten einer Entity; `Api.php` und `JoinBidirectionalType.php` benutzen nur noch sie.
- [ ] Der ungenutzte `AnnotationReader`-Import in `ApiController.php` ist entfernt, und es ist belegt, dass er ungenutzt war.
- [ ] Das Rückgabeformat ist unverändert — heute ein Array, dessen Schlüssel die vollen Klassennamen der Annotationen sind (`'Areanet\\PIM\\Classes\\Annotations\\Select'`). Jeder `Type` liest so, also bleibt es so.
- [ ] Kein Verhaltenswechsel: gelesen werden weiterhin Annotationen, die Suite ist ohne geänderte Zusicherung grün.

## Verification
Volle Suite. Dazu `grep` über `Classes/Types/`, dass die Schlüsselform des Arrays unverändert
ist — 21 Type-Klassen greifen darauf zu.
