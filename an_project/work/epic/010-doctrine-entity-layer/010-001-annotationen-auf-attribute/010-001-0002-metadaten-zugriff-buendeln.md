---
id: 010-001-0002
title: Den Zugriff auf die eigenen Metadaten an einer Stelle bündeln
status: done
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
- [x] Eine Klasse liefert die Klassen- und Property-Metadaten einer Entity; `Api.php` und `JoinBidirectionalType.php` benutzen nur noch sie.
- [x] Der ungenutzte `AnnotationReader`-Import in `ApiController.php` ist entfernt, und es ist belegt, dass er ungenutzt war.
- [x] Das Rückgabeformat ist unverändert — heute ein Array, dessen Schlüssel die vollen Klassennamen der Annotationen sind (`'Areanet\\PIM\\Classes\\Annotations\\Select'`). Jeder `Type` liest so, also bleibt es so.
- [x] Kein Verhaltenswechsel: gelesen werden weiterhin Annotationen, die Suite ist ohne geänderte Zusicherung grün.

## Verification
Volle Suite. Dazu `grep` über `Classes/Types/`, dass die Schlüsselform des Arrays unverändert
ist — 21 Type-Klassen greifen darauf zu.

## Ergebnis

**`Classes\Metadaten\Metadatenleser` ist der eine Zugang.** `Api.php` und
`JoinBidirectionalType.php` benutzen nur noch ihn; im ganzen Baum baut sich niemand mehr einen
eigenen `AnnotationReader` — geprüft per `grep`.

Er liest weiterhin **Annotationen**. Das ist der Punkt: Der Task ändert kein Verhalten, er
schafft die Stelle, an der `010-001-0003` umschaltet. Dieselbe Konstruktion wie
`Kernel\Application` gegenüber Silex in `009-001` — erst die Fuge, dann der Tausch dahinter.

### Was er bewusst **nicht** übernimmt

Das Rückgabeformat bleibt eine **Liste** in Deklarationsreihenfolge, genau wie beim
`AnnotationReader`. Die Umschlüsselung nach Klassennamen (`get_class()`) und das anschliessende
`krsort()` bleiben in `Api.php`.

Der Grund ist der Vertrag: **22 Dateien greifen mit 15 verschiedenen Schlüsseln** auf
`$propertyAnnotations['<voller Klassenname>']` zu. Diese Umschlüsselung hierher zu ziehen wäre
ein zweiter Umbau im selben Schritt gewesen — und sie ist der empfindlichste Teil, weil jede
`Type`-Klasse daran hängt.

### Ein Befund, grösser als der Task

Der Task nannte **einen** ungenutzten Import im `ApiController`. Nachgezählt sind es **21** —
darunter `Entity\Base`, `Entity\File`, `Permission`, `ArrayCollection`, `Criteria`,
`UniqueConstraintViolationException` und `AssignedGenerator`.

**Entfernt ist nur der eine, der zu diesem Task gehört.** Die übrigen 20 sind ein eigener
Aufräum-Task; sie hier mitzunehmen hiesse, einen Task unterwegs zu erweitern, und danach wäre
nicht mehr prüfbar, was er getan hat. Das ist derselbe Grund, aus dem `009-001-0003` seinen
`Knp\`-Befund als eigenen Task `009-001-0006` abgelegt hat statt ihn miterledigt.

### Eine Reihenfolge, die man im Blick behalten muss

`Api.php` verschickt je Metadatum das Ereignis `pim.schema.after.propertyAnnotation` — **vor**
dem `krsort()`, also in Deklarationsreihenfolge. Ein Projekt, das daran hängt, sieht diese
Reihenfolge. Unter Attributen ist sie ebenfalls die Deklarationsreihenfolge, sie bleibt also
gleich; für das Ergebnis selbst spielt sie ohnehin keine Rolle, weil `krsort()` danach eine
feste Ordnung herstellt. Festgehalten, weil `010-001-0003` genau hier vorbeikommt.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (267 tests, 639 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| `new AnnotationReader` ausserhalb des Lesers | 0 Treffer |
| Schlüsselform unverändert | 15 Schlüssel in 22 Dateien, keiner angefasst |
