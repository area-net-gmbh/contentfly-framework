---
id: 007-002-0003
title: Die PIM-Annotationen — sieben entfernen, die übrigen zu Attributen
status: review
depends_on: [007-002-0001]
---

# Die PIM-Annotationen — sieben entfernen, die übrigen zu Attributen

## Context
Mit Epic `012` ist der Teil der `@PIM`-Annotationen weggefallen, der Eingabemasken beschrieb.
Sieben Annotationen sind ersatzlos zu löschen; die Liste steht vollständig in
`an_project/docs/pim-annotationen-migration.md`, Abschnitt 1:

`@PIM\Rte` · `@PIM\Textarea` · `@PIM\Datetime` · `@PIM\Time` · `@PIM\Password` ·
`@PIM\MatrixChooser` · `@PIM\EntitySelector`

**Warum das nicht optional ist:** Ein stehengebliebenes Feld ist kein geduldetes Relikt.
`Doctrine\Common\Annotations\Annotation::__get()` wirft eine `BadMethodCallException`, und der
`AnnotationReader` bricht schon beim Einlesen ab. Das Projekt startet dann gar nicht.

**Die Story hat hier zu viel eigenen Anteil erwartet.** Sie sagte, für den `@PIM`-Teil gebe es
keinen fertigen Rector-Satz. Für das Entfernen einer **ganzen Annotation** gibt es einen:
`RemoveAnnotationRector` aus `rules/DeadCode/Rector/ClassLike/` ist konfigurierbar und nimmt die
Namen entgegen. Nachgesehen am 2026-09-11. Der eigene Anteil liegt bei den **Feldern** und damit
in `007-002-0004`.

**Mitentfernt gehören drei Type-Klassen** aus der Konfiguration: `RteType`, `PasswordType`,
`EntitySelectorType`. Ein Projekt, das eine davon in `APP_SYSTEM_TYPES` oder `APP_CUSTOM_TYPES`
aufführt, bricht beim Start mit `contentfly_type_class_not_found`. **Das ist keine
Entity-Änderung** und deshalb hier nur zu benennen, nicht zu automatisieren — die Regel läuft
über `Entity/`, nicht über die Konfiguration.

## Nachgetragen beim Umsetzen: die gebliebenen Annotationen müssen mit

**Der Schnitt der Story ging davon aus, dass die gebliebenen `@PIM\*`-Annotationen nicht
anzufassen sind. Das ist falsch, und der Grund ist ein stiller Ausfall.**

`Classes/Metadaten/Metadatenleser` liest seit `010-001-0003` **ausschliesslich PHP-Attribute**
per Reflection; der `AnnotationReader` ist aus dem Framework verschwunden. Ein Projekt, das nach
der Migration `@PIM\Config(excludeFromSync=true)` im Docblock behält, hat damit eine
Konfiguration, **die niemand mehr liest** — und es gibt keine Fehlermeldung:

- Eine Entity mit `excludeFromSync` landet wieder in der Sync-API.
- Ein Feld mit `encoded` wird unverschlüsselt geschrieben.
- Ein `isFilterable` verschwindet aus den API-Filtern.

Alles lautlos. Der ORM-Satz aus `007-002-0002` fasst die `@PIM`-Angaben nicht an, also braucht es
eine zweite Regel: `AnnotationToAttributeRector`, konfiguriert für die acht Annotationsklassen,
die bleiben (`Config`, `Select`, `Virtualjoin`, `Permissions`, `I18nPermissions`, `Checkbox`,
`Radio`, `ManyToMany`).

**Der Titel dieses Tasks ist deshalb erweitert worden**, und die Tasks-Liste der Story mit ihm —
ein Titel, der nur die Hälfte nennt, ist dieselbe Art Defekt wie ein Kommentar, der nicht mehr
stimmt.

## Acceptance criteria
- [x] `rector.php` entfernt die sieben Annotationen, konfiguriert aus der Liste und nicht aus dem Gedächtnis.
- [x] Der Prüfstein kommt ohne sie heraus, und die gebliebenen Annotationen behalten **jedes Feld und jeden Wert** — beides geprüft, nicht nur das erste.
- [x] **Nachgetragen:** Die gebliebenen Annotationen werden zu Attributen, weil das Framework nur noch Attribute liest und ein Docblock sonst lautlos wirkungslos wäre.
- [x] **Nachgetragen:** Die Regel hängt nicht am Alias `PIM` — geprüft an einer Datei, die die Annotationen unter einem anderen Namen importiert.
- [x] Dass die drei Type-Klassen aus der Konfiguration zu streichen sind, steht dort, wo der Aufruf beschrieben ist; es wird nicht automatisiert und der Grund dafür steht dabei.
- [x] Die volle Suite bleibt grün.

## Verification
Rector gegen den Prüfstein, Ergebnis gegen den Sollzustand. Der Gegentest ist der wichtigere:
Die gebliebenen Annotationen müssen Zeichen für Zeichen dieselben sein.

## Ergebnis

**Die sieben gestrichenen Annotationen sind weg, und die gebliebenen sind Attribute.**
`rector.php` trägt zwei konfigurierte Regeln: `RemoveAnnotationRector` für die sieben,
`AnnotationToAttributeRector` für die acht, die bleiben.

**Kein eigener Code, und damit hatte die Story nur halb recht.** Sie sagte, für den
`@PIM`-Teil gebe es keinen fertigen Rector-Satz. Für das Entfernen einer **ganzen** Annotation
gibt es einen, und er greift auch auf Eigenschaften — `getNodeTypes()` nennt `Property`
ausdrücklich. Der eigene Anteil liegt bei den **Feldern** und damit in `007-002-0004`.

**Vollqualifiziert statt `PIM\Rte`, und das ist nachgemessen.** Beide Schreibweisen
funktionieren, aber die kurze hängt am Alias. `tests/Fixtures/RectorMigration/alt/AndererAlias.php`
importiert die Annotationen als `Anders` und das Mapping als `Abbildung` — die Regel greift dort
genauso, weil Rector den vollen Namen über die `use`-Anweisungen auflöst. **Ohne diese Datei
wäre die Alias-Abhängigkeit nicht aufgefallen**, und ein Projekt mit eigenem Alias hätte den Lauf
für vollständig gehalten. Nebenbei belegt sie, dass Rector Importe nicht umräumt: Aus
`@Abbildung\Column` wird `#[Abbildung\Column]`, nicht `#[ORM\Column]`.

## Der Fund, der den Task grösser gemacht hat

**Die gebliebenen Annotationen mussten mit — sonst wäre die Migration ein stiller Ausfall.**
`Classes/Metadaten/Metadatenleser` liest seit `010-001-0003` ausschliesslich PHP-Attribute; der
`AnnotationReader` ist aus dem Framework verschwunden. Ein Projekt, das nach der Migration
`@PIM\Config(excludeFromSync=true)` im Docblock behält, hat eine Konfiguration, **die niemand
mehr liest** — ohne Fehlermeldung. Die Entity landet wieder in der Sync-API, ein
`encoded`-Feld wird unverschlüsselt geschrieben, ein `isFilterable` verschwindet aus den
Filtern.

Aufgefallen ist es an einem Testfehlschlag: Die Alias-Probe behielt nach dem Lauf
`@Anders\Config(…)` im Docblock, und der ORM-Satz fasst PIM-Angaben nicht an. **Titel und
Kriterien dieses Tasks sind erweitert**, die Tasks-Liste der Story mit ihnen — ein Titel, der nur
die Hälfte nennt, ist dieselbe Art Defekt wie ein Kommentar, der nicht mehr stimmt.

## Was die Regel nicht kann, steht jetzt im Kopf von `rector.php`

Die drei entfallenen Type-Klassen (`RteType`, `PasswordType`, `EntitySelectorType`) stehen in
`custom/config.php`, nicht in einer Entity. Eine Regel über `Entity/` kommt dort nie vorbei, und
sie zu erweitern hiesse, die Konfiguration eines Projekts umzuschreiben. Ein Projekt, das eine
davon in `APP_SYSTEM_TYPES` aufführt, bricht beim Start mit `contentfly_type_class_not_found`
ab — das gehört genannt, damit niemand den Lauf für vollständig hält. `007-002-0005` sammelt
die vollständige Liste.

## Vier Fehlgriffe in meinen eigenen Tests

Alle vier von derselben Art: **eine Prüfung, die grün ist, weil sie nichts sieht.**

1. **`array_merge` bei Zeichenketten-Schlüsseln ersetzt, statt anzuhängen.** Der Vergleich der
   gebliebenen Annotationen sah damit nur die *letzte* Datei und war für die anderen beiden
   blind.
2. **Zwei Prüfungen hingen am Alias `PIM`** — `wirksameZeilen()` und der Suchausdruck für die
   Felder. Genau die Datei, die die Alias-Unabhängigkeit belegen soll, fiel durch ihr Raster.
3. **Die ORM-Prüfung hing am Alias `ORM`** und meldete die Alias-Probe als „kein ORM-Attribut
   entstanden", obwohl dort `#[Abbildung\Column]` stand.
4. **Der Vergleich verlangte Gleichheit Zeichen für Zeichen.** Er wurde rot, sobald aus
   `label="Artikel"` ein `label: 'Artikel'` wurde — also genau dann, wenn die Regel richtig
   arbeitet. Verglichen werden jetzt **Felder und Werte**, normalisiert und sortiert, weil die
   Schreibweise wechseln *soll* und die Reihenfolge nichts aussagt.

**Zahlen:** Volle Suite `OK (509 tests, 1336 assertions)` (vorher 506), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. Der Prüfstein bleibt nach allen Läufen
unverändert.
