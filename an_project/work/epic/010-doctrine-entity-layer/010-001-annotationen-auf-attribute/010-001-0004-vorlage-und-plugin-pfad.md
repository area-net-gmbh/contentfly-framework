---
id: 010-001-0004
title: Die Vorlage umstellen und den Plugin-Pfad entscheiden
status: done
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
- [x] Die Kommentare der Vorlage erklären den Attribut-Weg, nicht den alten — einschliesslich der Stelle, an der `@PIM\Select` seine Optionen deklariert.
- [x] `Classes/Plugin.php` benutzt denselben Treiber wie der Rest; dass der Pfad mangels Plugin nicht ausführbar ist, steht als Einschränkung im Ergebnis und als Kommentar an der Stelle.
- [x] `VorlageApiTest` bleibt grün und prüft die Vorlage weiterhin über HTTP.

## Verification
`./vendor/bin/phpunit --filter VorlageApiTest`, dann die volle Suite. Zusätzlich ein Schreib- und
Lesevorgang gegen `example_entity` über die API, damit die Umstellung nicht nur beim Lesen des
Schemas belegt ist.

## Ergebnis

**Die Vorlage erklärt den Attribut-Weg, und der Plugin-Pfad ist mitgezogen — mit einem Test, von
dem ich zunächst behauptet hatte, es gäbe ihn nicht.**

### Der Plugin-Pfad war nicht wahlfrei

`Classes/Plugin.php` baute für jedes Plugin einen eigenen `newDefaultAnnotationDriver()`. Der
Task fragte, ob er mitgezogen wird. Die Antwort ist erzwungen, nicht abgewogen: Eine
Plugin-Entity erbt von `Areanet\PIM\Entity\Base`, also greift dieselbe Mechanik wie in
`010-001-0003` — bei einer `MappedSuperclass` liest der Treiber der Unterklasse die geerbten
Felder neu, und ein Annotation-Treiber fände an der umgestellten `Base` nichts mehr.

### Die Suite hat mich korrigiert

Ich hatte in den Code geschrieben, der Pfad sei „mitgezogen, nicht erprobt — kein Testlauf
berührt ihn". **Das war falsch**, und der nächste Suite-Lauf hat es sofort gezeigt:
`PluginManagerTest::testUseOrmRegistriertEinenAnnotationDriverFuerDasPluginVerzeichnis` wurde
rot, weil der Spion auf `newDefaultAnnotationDriver()` lauerte und die Methode nicht mehr
gerufen wird.

Der Test ist **umgedreht**, nicht repariert — der Treibertyp ist Teil dessen, was er zusichert:

| Vorher | Nachher |
|---|---|
| `…RegistriertEinenAnnotationDriverFuerDasPluginVerzeichnis` | `…RegistriertEinenAttributeDriverFuerDasPluginVerzeichnis` |
| Spion fängt `newDefaultAnnotationDriver()` ab und merkt sich die Pfade | Spion merkt sich den **Treiber** aus `addDriver()` und liest `getPaths()` |

Die abgefangene Methode steht **nicht** mehr im Spion. Riefe der Code sie doch, stürbe er an
einer undefinierten Methode — laut ist besser als still.

Der Kommentar im Code ist entsprechend richtiggestellt: Der Test deckt ab, dass der richtige
Treiber unter dem richtigen Namensraum eingehängt wird. Was er **nicht** zeigt, ist, dass
Doctrine aus einer echten Plugin-Entity danach Metadaten liest — `plugins/` ist leer, seit
`006-003-0002`.

### Ein Befund aus der Vorlage: ein Index, den es nie gab

`custom/Entity/Core/Example.php` deklariert `idx_example_slug`. **Er existiert in der Datenbank
nicht** — DBAL lässt einen Index weg, den ein vorhandener bereits erfüllt
(`Table::isFulfilledBy()`), und der Unique-Index auf derselben Spalte tut das.

Nachgemessen mit `SHOW INDEX`, **vor und nach** der Umstellung: identisch. Es ist also kein
Verlust durch die Attribute, sondern eine Deklaration, die von Anfang an inert war. Sie bleibt
stehen, weil sie die Schreibweise vorführt; der Grund steht jetzt daneben.

### Nachweis

| Probe | Ergebnis |
|---|---|
| **Erzeugte Datenbank, vorher gegen nachher** | **211 Spalten und 67 Indexzeilen — kein Unterschied** |
| `VorlageApiTest` | `OK (13 tests, 55 assertions)` |
| Anlegen und Lesen über HTTP gegen `example_entity` | 1 Datensatz, `jsonExample` als verschachteltes Objekt zurück, `boolExample` mit Vorgabe `false` |
| `#[PIM\Select]` prüft weiterhin | `contentfly_general_invalid_params` bei einem Wert ausserhalb der Liste |
| Volle Suite | `OK (267 tests, 640 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |

Der Datenbankvergleich ist der eigentliche Beleg dieses Tasks. Der Schema-Vergleich aus
`010-001-0003` sieht `$app['schema']`, aber keine Indizes — genau dort sass die offene Frage.

**Zwei Statuscodes am Rande, beide älter als dieser Task und nicht angefasst:** Ein Wert
ausserhalb der `Select`-Liste ergibt **500** statt 400, und eine Verletzung des
Datenbank-Unique-Index ergibt `contentfly_general_unknown_perror` mit **500** statt 409 — die
409-Behandlung aus `009-003-0002` hängt an `@PIM\Config(unique=true)`, nicht an einem Index in
der Tabellendefinition.
